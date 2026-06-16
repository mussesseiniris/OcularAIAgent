# OcularAIAgent — Code & Design Audit

**Date:** 12 June 2026
**Scope:** Full review of the TYPO3 v13 RAG chatbot extension (`packages/chatbot`), ingestion scripts, frontend widget, and system configuration. Review only — no code changes made.

**Summary:** The architecture is sensible and the prompt/XSS hygiene is better than most projects of this kind, but there are two production-breaking bugs (the `$_SESSION` Turnstile flow and the rate-limit window typo), one committed secret, and a prompt-injection hole via client-supplied history. The scraping-based ingestion should be replaced with direct DB queries once the extension runs inside the real ocular.nz TYPO3 instance.

---

## 1. Critical / security

### 1.1 Committed secrets — `config/system/settings.php:106`

(Note from Ben: this is correct, but the real website already handles this properly, so mostly just FYI here - fix it if you want to) The TYPO3 `encryptionKey` (and the install-tool password hash at line 5) are committed to git. The encryption key is used for cache hashes, cookie signing, and various HMACs — if this repo is ever shared or this config reaches production, it must be rotated and injected via environment instead. The `db/db/db` credentials are DDEV defaults and harmless, but ideally `settings.php` wouldn't be committed at all (use `additional.php` reading env vars, or gitignore it).

### 1.2 Turnstile session verification doesn't persist — `ChatController.php:41,60`

TYPO3 frontend never calls `session_start()`, so `$_SESSION['turnstile_verified'] = true` lives only for that single request and reads back as `false` on the next one. Meanwhile the frontend (`Chatbot.js:107-110`) sets `humanVerified = true` after the first reply and stops sending tokens. Net effect in production with a real secret key: **message 1 succeeds, message 2 fails verification forever** (Turnstile tokens are single-use). This is invisible in dev because the test keys always pass and a missing secret key skips verification entirely.

**Recommended fix (preferred):** drop server-side session state altogether and verify a fresh token per message. The widget already uses `execute()` mode, so the frontend can call `turnstile.execute()` before every send and the backend verifies statelessly. This is the most idiomatic use of Turnstile (it verifies discrete actions, not sessions), it eliminates the session-persistence problem rather than patching it, and the per-message latency cost (a few hundred ms) is negligible for a chat widget.

If not doing the above: **Fix the session flag properly** using TYPO3's frontend user session API (`$request->getAttribute('frontend.user')->setKey('ses', ...)`). Note this changes the security property: you verify session _establishment_, not each message, so the rate limit becomes the real backstop.

Regardless of pattern: Turnstile only proves "a real browser ran the JS and didn't look like a bot at that moment." It is not authentication and not rate limiting; the per-IP limit remains the primary cost control.

### 1.3 Turnstile fails open — `ChatController.php:94-97`

If `TURNSTILE_SECRET_KEY` is unset, verification is silently skipped. A forgotten env var in production disables bot protection with only an `error_log` line to show for it. Fail closed, or at minimum log at error severity with alerting.

### 1.4 Client-supplied `history` goes straight into the LLM — `ChatController.php:74-76`, `ChatService.php:297-301`

(This one is critical - Ben) The browser sends `history` as JSON and it is merged verbatim into the message array. A user can craft entries with `"role": "system"` to override the persona/guardrail prompt, impersonate prior assistant turns, or pad the history to arbitrary size (token cost abuse — at 200 questions/day/IP with unbounded payloads, that's real money on Groq/Voyage). Preferred fix: store the transcript server-side in the TYPO3 frontend session (frontend.user->setKey('ses', …)) and accept only the new question from the client; cap retained turns before sending to the LLM.

The general rule: **the server must own any state it relies on; the client may hold state only as a hint, never as the record.**

### 1.5 Exception details returned to end users — `ChatController.php:84-86`

`'answer' => 'DEBUG: ' . $e->getMessage()` leaks internal paths, hostnames (`qdrant:6333`), and API error bodies to any visitor. Return a generic message; log the detail server-side.

### 1.6 Rate limiting weaknesses — `RateLimitService.php`

- **The window is 10 days, not 24 hours:** `WINDOW = 864000` (line 15) — one zero too many; 24h is 86400. The comment, README, and `CleanupRateLimitCommand` all say 24 hours. As written, the in-table reset (line 53) almost never fires and the actual reset silently depends on the cleanup cron deleting the row.
- **Read-then-write race:** two concurrent requests both read count N and both write N+1. Use an atomic `UPDATE ... SET question_count = question_count + 1`.
- **`$_SERVER['REMOTE_ADDR']` used directly** (`ChatController.php:33`): behind Cloudflare/a reverse proxy this is the proxy's IP, so all visitors share one bucket. Use TYPO3's `normalizedParams->getRemoteAddress()`, which honors `reverseProxyIP` config.
- The counter increments **before** Turnstile verification and even when no `question` argument is present, so unverified bots burn a legitimate user's quota.
- Unsalted `sha256(ip)` is not meaningfully "for privacy" — the IPv4 space is enumerable. Use an HMAC with a server-side secret.

### 1.7 User content logged liberally

`ChatService.php` logs every question, retrieved chunk previews, and scores via `error_log`; the controller logs all Turnstile traffic. That's PII in flat server logs with no rotation or levels. Use TYPO3's PSR-3 `LoggerInterface` at debug level so production can turn it off. (This is a good one for all your uses of error_log - TYPO3's own logging system is much preferred - Ben)

---

## 2. Functional bugs

- **`Services.yaml:21`** registers `LLPhant\Embeddings\EmbeddingGenerator\VoyageAI\Voyage4EmbeddingGenerator` — that class doesn't exist (the project's class is `Ocular\Chatbot\Embeddings\Voyage4EmbeddingGenerator`, already covered by the `Ocular\Chatbot\` resource block). Dead or container-breaking; either way wrong.
- **`ArticlesCrawler.php:28,36`** — duplicate array key `'online projects'`; the `'Web Development'` mapping is silently discarded by PHP.
- **`ArticlesCrawler.php:117-123`** — the else-branch pushes every _non-matching_ category into `$tags`, so an "Industry Insights" article gets tags `['Updates', 'Live Work Bay']`. Harmless only because `buildChunks()` later overwrites tags with `detectTags()` — which makes the listing-page tag logic dead and misleading. Similarly `$detail['title']` (line 271) is never returned by `getArticleDetail()`, so that fallback is dead code.
- **`ServiceCrawler.php:22`** — `'Emerging Technology' => ''` produces chunks with `service_types: ['']` in Qdrant.
- **`PositioningPdfCrawler.php`** — empty `entityId`/`entityName` yield chunk IDs like `chunk__company_introduction`; the hardcoded page indices `[3, 4, 6]` break the moment the PDF is re-exported. Also imports `PhpParser\Node\Scalar\MagicConst\Dir` — a stray IDE auto-import.
- **`ChatService.php:35-36`** — comment says "Use new QueryRequest instead of deprecated SearchRequest"; the code uses `SearchRequest`.
- **`Chatbot.js:114`** — on a malformed response, `data.answer` (possibly `undefined`) is pushed into history instead of the fallback string used for display.
- **`ingest-data.php:93`** — `usleep(21000000)` is 21 seconds per chunk under a comment saying "Small delay" (~35 min for ~100 chunks). If this targets Voyage's free-tier RPM limit, say so — and batch instead (the Voyage API accepts arrays of inputs; `Voyage4EmbeddingGenerator.php:23` already wraps single texts in an array).
- **`Voyage4EmbeddingGenerator.php:40`** — `?? []` swallows API errors; `ChatService::search()` then sends an empty vector to Qdrant and fails confusingly downstream. Throw on non-200 / missing embedding (the ingest script checks; the chat path doesn't).

---

## 3. Architecture & design

### 3.1 Retrieval / intent detection — weakest design area

`ChatService::search()` (`ChatService.php:29-208`) is ~180 lines mixing keyword-based intent detection, three vocabularies, filter assembly, and the search itself:

- The vocabularies (service types, tags, synonyms) are **duplicated and already drifting** across `ChatService`, `ArticlesCrawler`, `ServiceCrawler`, and `AboutUsCrawler`. One shared vocabulary class/config file would fix the drift and shrink all five files.
- `stripos` substring matching is brittle: `'how'` → Process fires on "show me…", `'who'` → person fires on "who do you work with?", `'graphy'` is a fragment hack. Worst, detected entity types become a **`must`** filter (`ChatService.php:194`) — a false positive _excludes_ chunks that would have matched by vector similarity. Demote to `should`, or replace keyword detection with one cheap LLM classification call that emits the filters, or let the embeddings do the work.
- `$this->qdrantVectorStore->client` (line 202) reaches through the store's public property and re-hardcodes `'ocular_chunks'` and the `'openai'` vector name already configured in DI. Worth a small wrapper.

### 3.2 Vector store sizing

Qdrant works, but at this corpus size (a few hundred 1024-dim vectors) the _database_ part of "vector database" is barely earning its keep — the load-bearing technology is the embeddings. The same vectors could live in Postgres/pgvector or even be brute-force cosine-scanned in PHP, removing a service from the deployment. A dedicated vector DB earns its complexity at millions of vectors (ANN indexes) or with substantial knowledge-base growth plans. Not urgent to change — but a deliberate decision worth recording, since it was likely chosen simply because LLPhant ships a Qdrant integration. If retrieval quality becomes a focus, hybrid search (BM25 keyword + vector) typically beats pure vector, especially for exact names and identifiers.

### 3.3 Controller doing too much

`askAction` handles rate limiting, Turnstile, argument parsing, and response shaping; `verifyTurnstile` news up a Guzzle client inline. Extract a `TurnstileService` (injectable, testable, Guzzle from DI). Rate-limit and verification failures currently return HTTP 200 with the error stuffed into `answer` — the frontend can't distinguish them. Use 429/403 with an `error` field; that also fixes the frontend state bug where a failed verification leaves a consumed single-use token in `turnstileToken`, making every retry fail until page reload.

### 3.4 Ingestion: scrape vs. database (recommended change)

The crawlers scrape ocular.nz over HTTP, but everything being scraped except the PDF lives in CMS tables this extension could query directly once installed in the real instance:

| Crawler         | Scraped from                                         | Actual DB source                                                                                                               |
| --------------- | ---------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| ProjectsCrawler | `div.ce-bodytext p`, meta description, `data-groups` | `tt_content.bodytext`, `pages.description`, `sys_category` relations (or a custom project extension's tables)                  |
| ArticlesCrawler | Listing pagination, h4 cards, meta descriptions      | `tx_news` or page records: title, teaser, body, categories, slug as clean fields                                               |
| ServiceCrawler  | h2/h3/p state machine on `/services/`                | `tt_content.header`/`subheader`/`bodytext` in order                                                                            |
| AboutUsCrawler  | `strong`/`i` inside `ce-bodytext`                    | Still RTE HTML in `tt_content.bodytext` — parsing remains, but from the field, without nav/footer noise or double HTTP fetches |

What the DB approach buys: robustness (no silent breakage on frontend redesigns), correct visibility semantics (enableFields: hidden/deleted/timed records), **incremental re-indexing** via DataHandler hooks (saving a record re-embeds just its chunks — no more manual full re-crawls), and no self-inflicted load/rate-limit choreography. The `processArticleUrlMap` hack (hardcoded URL→ID maps for cross-references) dissolves into record UIDs; the "split data-groups into serviceTypes vs tags against a hardcoded list" heuristic dissolves into reading the category tree.

The crawlers' _chunking logic_ (what becomes a chunk, what metadata it carries) is worth keeping; only the data source should change. Caveat: this repo contains only the dev distribution, so the production schema is inferred from rendered HTML — verify against the production extension list. If DB/repo access to production is genuinely never going to happen, keep scraping but add schema-drift detection: fail loudly when expected selectors return empty rather than ingesting silence.

### 3.5 Ingestion should be a console command

`ingest-data.php` lives at repo root, selects crawlers by commenting code in and out, and duplicates DI wiring by hand. The pattern already exists in `CleanupRateLimitCommand` — a `chatbot:ingest` command with a `--crawler` option would use the container's config and be schedulable. The `test-*.php` scripts (root and `packages/chatbot/test-filter.php`) are manual probes, not tests: delete them or convert the pure-logic parts (tag detection, filter building, article-ID derivation) into PHPUnit tests — those are the functions where the bugs are currently hiding.

### 3.6 System prompt

Genuinely good — tone rules, the anti-injection rule 0.9, the `<knowledge>` data/instruction separation, URL-citation discipline. Two suggestions: it's a 60-line string inside PHP, painful for non-developers to edit — move it to a text file or TypoScript; and the contact emails/URLs are hardcoded in both the prompt and the rate-limit message, so they will drift.

### 3.7 Miscellaneous

- `ChatWidget.html` is injected via `page.footerData` on _every_ page (`setup.typoscript:41`), making the registered plugin/TCA element mostly vestigial — and the broken legacy `Ask.html` template (stray `</textarea>`, duplicate `id`) is dead weight. (Note - on the real site we would actually WANT the registered plugin rather than the inline typoscript inclusion, so don't remove it - Ben)
- Crawlers `echo` progress from library code ("Scrapping…" — typo in three files); pass an output callback or logger.
- `AboutUsCrawler` fetches `/about-us/` twice.
- Large commented-out blocks: `Chatbot.js:1-30`, `ext_localconf.php:27-37`, most of `test-crawler.php` and `ingest-data.php`'s crawler list. Unused `use Doctrine\DBAL\Schema\UniqueConstraint` in `ChatService.php:5`.
- `public/typo3temp/assets/css/…` is a committed generated file; `Resources/Private/Test-chatbot.html` is a leftover.
- README drift: says "Current rate limit = 10" (code says 200), has a `<YOUR_GITHUB_REPO_URL>` placeholder, and documents a manual `CREATE TABLE` that `ext_tables.sql` already handles.

---

## 4. UX / frontend

The widget is solid for a v1 — clean FAB/panel pattern, markdown rendering with **DOMPurify sanitization (the single most important XSS decision here, done right)**, mobile breakpoint present. Gaps:

- **Send isn't disabled while a request is pending** — users can fire overlapping requests and replies interleave out of order; the input stays active. Disable + show pending state.
- **Chat history vanishes on navigation.** Every page load resets the conversation and re-runs Turnstile. The fix follows from §1.4: once the transcript is server-owned, the widget should rehydrate its visible messages from the session on page load (a small "get transcript" endpoint, or render it into the widget server-side). Without that rehydration step, §1.4 actually makes this _worse_ — the model would still remember the conversation while the UI looks empty, which feels broken. No client-side storage (`sessionStorage` etc.) needed or wanted: the session is the single source of truth and the DOM is just a view of it. The Turnstile re-run on navigation resolves itself if going per-message tokens (§1.2).
- **Accessibility:** the messages container has no `aria-live="polite"` (screen readers never hear answers), the panel isn't `role="dialog"` with focus management, and Esc doesn't close it. The aria-labels on FAB/close are a good start.
- Rate-limit and error states render as ordinary bot messages — with proper HTTP statuses (§3.3) they could be styled distinctly and, e.g., disable input once the daily limit is hit.
- `console.log` left in production JS (`Chatbot.js:64`).
- marked + DOMPurify are vendored — good hygiene, but establish a pin/update process.

---

## 5. Suggested priority order

1. Fix Turnstile (production-breaking): move to per-message token verification (§1.2) and fail closed (§1.3).
2. Remove/rotate the committed encryption key (§1.1).
3. Validate `history` (roles, size) and cap question length; stop echoing exception messages (§1.4, §1.5).
4. Fix `WINDOW` to 86400, make the increment atomic, use the normalized client IP (§1.6).
5. Extract the shared vocabulary + intent detection out of `ChatService::search()`; fix the crawler bugs (§2, §3.1).
6. Plan the move from scraping to DB-backed ingestion as a console command with DataHandler-hook incremental updates (§3.4, §3.5).
7. UX pass: pending-state on send, aria-live, transcript rehydration from the server session, proper error statuses (§4).

Items 1–4 are small, contained changes; items 5–6 are the refactors with real surface area.
