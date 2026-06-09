<?php

namespace Ocular\Chatbot\Service;

use Doctrine\DBAL\Schema\UniqueConstraint;
use Ocular\Chatbot\Embeddings\Voyage4EmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\Qdrant\QdrantVectorStore;
use LLPhant\Chat\OpenAIChat;
use Qdrant\Models\Filter\Condition\MatchAny;
use Qdrant\Models\Filter\Filter;
use Qdrant\Models\Request\SearchRequest;
use Qdrant\Models\VectorStruct;


class ChatService
{

    private Voyage4EmbeddingGenerator $voyage4EmbeddingGenerator;
    private QdrantVectorStore $qdrantVectorStore;
    private OpenAIChat $chat;

    public function __construct(Voyage4EmbeddingGenerator $voyage4EmbeddingGenerator, QdrantVectorStore $qdrantVectorStore, OpenAIChat $chat)
    {
        $this->voyage4EmbeddingGenerator = $voyage4EmbeddingGenerator;
        $this->qdrantVectorStore = $qdrantVectorStore;
        $this->chat = $chat;
    }

    public function search(string $question, int $limit = 6): array
    {
        // Generate embedding for the question
        $questionEmbedding = $this->voyage4EmbeddingGenerator->embedText($question);
        error_log('[ChatService] embedding count: ' . count($questionEmbedding));

        // Use new QueryRequest instead of deprecated SearchRequest
        $searchRequest = (new SearchRequest(new VectorStruct($questionEmbedding, 'openai')))
            ->setLimit($limit)
            ->setWithPayload(true);

        $synonymServiceTypeMap = [
            'website development' => 'Platforms',
            'web development' => 'Platforms',
            'platform' => 'Platforms',
            'ux' => 'Experiences',
            'experience' => 'Experiences',
            'design' => 'Experiences',
            'graphy' => 'Communication',
            'brand' => 'Communication',
            'branding' => 'Communication',
            'video' => 'Communication',
        ];

        $knownServiceTypes = [
            'Platforms',
            'Communication',
            'Experiences',
        ];

        $knownArticleTypes = ['Industry Insights', 'Updates', 'Live Work Bay'];

        $detectedServiceTypes = [];
        foreach ($knownServiceTypes as $serviceType) {

            if (stripos($question, $serviceType) !== false) {
                $detectedServiceTypes[] = $serviceType;
            }
        }

        foreach ($synonymServiceTypeMap as $synonym => $serviceType) {

            if (stripos($question, $synonym) !== false) {
                $detectedServiceTypes[] = $serviceType;
            }
        }
        $detectedServiceTypes = array_values(array_unique($detectedServiceTypes));


        $detectedArticleTypes = [];
        foreach ($knownArticleTypes as $articleType) {
            if (stripos($question, $articleType) !== false) {
                $detectedArticleTypes[] = $articleType;
            }
        }
        $detectedArticleTypes = array_values(array_unique($detectedArticleTypes));


        $synonymTagsMap = [
            // Existing project synonyms
            'brand'                          => 'Brand',
            'branding'                       => 'Brand',
            'UX'                             => 'UX Design',
            'video design'                   => 'Video',
            'graphy'                         => 'Graphic Design',
            'photo'                          => 'Graphic Design',
            'web'                            => 'Web Development',
            'website'                        => 'Web Development',
            'website design'                 => 'Web Design',
            'web development'                => 'Web Development',
            'event'                          => 'Campaign',
            'customer relationship management' => 'CRM',
            'platform'                       => 'Platform Architecture',

            // Article-specific synonyms
            'strategy'                       => 'Strategy',
            'process'                        => 'Process',
            'design thinking'                => 'Design Thinking',
            'digital'                        => 'Digital',
            'how to'                         => 'Guide',
            'opinion'                        => 'Opinion',
            'explainer'                      => 'Explainer',
            'insight'                        => 'Agency Insight',
            'approach'    => 'Process',

            'development' => 'Process',
            'how'         => 'Process',
        ];

        $knownTags = [
            // Existing project tags
            'Video',
            'UX Design',
            'Brand',
            'Graphic Design',
            'Web Development',
            'Web Design',
            'Campaign',
            'CRM',
            'Platform Architecture',

            // Article-specific tags
            'Strategy',
            'Process',
            'Design Thinking',
            'Digital',
            'Guide',
            'Opinion',
            'Explainer',
            'Agency Insight',
        ];


        $detectedTags = [];
        foreach ($knownTags as $tag) {

            if (stripos($question, $tag) !== false) {
                $detectedTags[] = $tag;
            }
        }

        foreach ($synonymTagsMap as $synonym => $tag) {

            if (stripos($question, $synonym) !== false) {
                $detectedTags[] = $tag;
            }
        }
        $detectedTags = array_values(array_unique($detectedTags));

        $filter = new Filter();
        if (!empty($detectedServiceTypes)) {
            $filter->addShould(new MatchAny ('service_types', array_values($detectedServiceTypes)));
        }
        if (!empty($detectedTags)) {

            $filter->addShould(new MatchAny ('tags', array_values($detectedTags)));
        }

        if (!empty($detectedArticleTypes)) {
            $filter->addShould(new MatchAny ('article_type', array_values($detectedArticleTypes)));
        }

        if (!empty($detectedServiceTypes) || !empty($detectedTags) || !empty($detectedArticleTypes) || !empty($detectedProcess)) {
            $searchRequest->setFilter($filter);
        }

    
        $response = $this->qdrantVectorStore->client
            ->collections('ocular_chunks')
            ->points()
            ->search($searchRequest);

        return $response->__toArray()['result'];
    }

    public function ask(string $question, array $history): string
    {
        try {
        //step 1: Get relevant chunks from vetor databasde(Qdrant)
        $results = $this->search($question);

        error_log('[ChatService] Query: ' . $question);
        error_log('[ChatService] Retrieved ' . count($results) . ' chunks:');
        foreach ($results as $i => $doc) {
            $payload = $doc['payload'];
            $score   = $doc['score'] ?? 'n/a';
            error_log(sprintf(
                '[ChatService] [%d] score=%.4f | type=%s | name=%s | tags=%s',
                $i + 1,
                $score,
                $payload['entity_type'] ?? '',
                $payload['entity_name'] ?? '',
                implode(', ', $payload['tags'] ?? [])
            ));
            error_log('[ChatService] [' . ($i + 1) . '] content_preview: ' . mb_substr($payload['content'] ?? '', 0, 200));
        }

        //step 2: Build context from chunks
        $entityType = implode("\n\n", array_map(fn($doc) => $doc['payload']['entity_type'], $results));
        $entityName = implode("\n\n", array_map(fn($doc) => $doc['payload']['entity_name'], $results));
        $context = implode("\n\n", array_map(fn($doc) => $doc['payload']['content'], $results));
        $tags = implode("\n\n", array_map(fn($doc) => implode(', ', $doc['payload']['tags'] ?? []), $results));
        $relatedArticles = implode("\n\n", array_map(fn($doc) => implode(', ', $doc['payload']['related_articles'] ?? []), $results));
        $entityIds = implode("\n\n", array_map(fn($doc) => $doc['payload']['entity_id'] ?? '', $results));
        $url = implode("\n\n", array_map(fn($doc) => $doc['payload']['url'] ?? '', $results));

        $systemPrompt = "## Role & Purpose
        You are a website support chatbot for Ocular.
        Answer visitor questions using ONLY the knowledge base content provided below.
        Do not use outside knowledge or make assumptions beyond the indexed content.\n\n"
            . "## Tone & Voice
        Communicate in a way that reflects Ocular's character: intelligent, collaborative, calm, human, and strategically grounded.
        Your tone should convey that Ocular understands complexity, thinks strategically, collaborates closely, communicates clearly, designs thoughtfully, and delivers practical outcomes.
        Do NOT sound like a corporate consultancy, a startup SaaS company, or a generic creative agency.
        Keep responses clear and direct — no jargon, no hype, no filler phrases.
        Speak like a partner, not a supplier. Say \"we work alongside\" not \"we deliver\" or \"we provide services\".
        Avoid jargon like \"synergistic\", \"cutting-edge\", \"revolutionary\", \"end-to-end solutioning\".
        Frame creativity and design as tools for solving problems, not as standalone achievements.
        When an answer has multiple points or steps, use short bullet points rather than long paragraphs — visitors prefer clear, scannable, easy-to-follow answers. Keep responses concise; don't pad them out.
        You can be warm, personable, and a little playful — Ocular is professional but casual, never stiff or robotic. A touch of dry, understated humour is welcome when the moment suits it. Match the user's energy: play along briefly if they're being lighthearted, but stay focused and genuinely helpful for real questions. Never be goofy, sarcastic, or unprofessional.
        Always speak as Ocular in the first person — use 'we', 'us', and 'our', never 'they', 'their', or 'Ocular's' as if describing someone else. You ARE Ocular talking to the visitor, not a third party describing Ocular.\n\n"
            . "## Known facts about Ocular
            Contact email (new enquiries / general): results@ocular.nz
            Technical support email (existing clients): support@ocular.nz
        Website: https://ocular.nz
        'It's All About Brand' eBook download: https://ocular.nz/article/its-all-about-brand-free-ebook/
        (For general questions and new enquiries — or whenever you invite the user to discuss a project, talk further, or get in touch — include the contact email results@ocular.nz and mention they can also use the Contact Us form at https://ocular.nz. Don't end on a vague 'we're here to help' without giving them a concrete way to reach the team. EXCEPTION: for technical or account/login problems, use support@ocular.nz instead, as covered in the rules below.)\n\n"
            . "## How to answer
        0. If the user is just greeting you (e.g. 'hi', 'hello', 'thanks') or making small talk, respond naturally and briefly, and invite them to ask a question about Ocular. This does NOT require any knowledge base content.
        0.5. If the user asks a follow-up question that refers to something already discussed (e.g. uses words like 'that', 'it', 'those', 'the project', 'what about it'), answer ONLY from the conversation history above. Do NOT use the knowledge base content below for such questions, as it may be about a different topic entirely.
        0.7. If the user asks something clearly silly, absurd, or obviously not a genuine enquiry (a joke, a riddle, a far-fetched complaint like someone on the team harming their pet), respond with light, good-natured humour that shows Ocular's casual side, then gently steer them back to how you can actually help with Ocular. This also applies to clearly mistaken-identity questions (e.g. someone confusing Ocular with an unrelated business like an optician) — keep a light, friendly touch while politely clarifying that this Ocular is a branding and design agency, and point them to https://ocular.nz in case that's the Ocular they were looking for. 
            CRITICAL: when a complaint is clearly far-fetched or absurd (e.g. claiming a team member harmed someone's pet), do NOT treat it as a real event, do NOT say you 'take it seriously', and do NOT offer to follow up or resolve it — that would imply the made-up incident actually happened. Instead, lightly and good-naturedly make clear it didn't happen, then steer back to how you can help. When the complaint names a real person, don't repeat or confirm their name — speak generally (e.g. 'no one at Ocular') rather than tying a real individual to the made-up claim. Never invent facts about Ocular, its people, or events, and never invent a link. Stay playful but truthful.
        0.8. If the user describes a technical or account problem with their existing website (e.g. can't log in, locked out, password/MFA reset, access issues, something not working), do NOT try to solve it or guess steps. Warmly direct them to email support@ocular.nz, where Luke and the team can help. Use support@ for technical problems, NOT results@.
        1. First, judge whether the provided context actually addresses the user's question.
        2. If the context directly and clearly answers the question, respond directly and confidently using that content.
        3. If the context is NOT relevant to the question, or only loosely related, first check whether the conversation history already contains enough information to answer. If yes, use the history to answer.
        If neither the context nor the history can answer it, don't just say you don't have the information. Instead, respond warmly and invite the user to talk it through with the team — for example, that Ocular would love to discuss what they're looking for and how they can help. Keep it conversational, not a flat referral.
        4. Never mix unrelated topics. For example, a question about pricing must NOT be answered using content about wages, salaries, or unrelated articles just because they share a similar word.
        5. Only state facts explicitly supported by the context. Do NOT fill gaps with plausible-sounding generalisations, statistics, or company history (e.g. years in business, number of projects) that aren't in the context. When giving examples of clients or projects, only name actual external clients Ocular has worked with — never list Ocular itself as a client, case study, or example, since Ocular provides the services rather than being its own customer. If you don't have the information, say so honestly and warmly, then invite them to talk to the team — a truthful 'I can't speak to that' is always better than an impressive-sounding guess.
        6. Never refer to your knowledge base, context, or source material as something visible to you, anywhere in the answer — not just at the start. Do NOT use phrases like 'Based on the provided context', 'According to the context', 'based on the projects/case studies highlighted', 'from the information available', 'there is no mention of X', or 'the information doesn't include'. The visitor must never sense there is a body of 'provided' content behind you. Just answer directly and naturally, as if you simply know the answer.
        7. If the user's wording is a close or informal variant of something in the knowledge base (e.g. a name spelled or spaced differently, a nickname, a typo), treat it as referring to that same thing and answer directly. Do NOT point out that you couldn't find an exact match, and do NOT narrate what is or isn't mentioned in your information — just answer as if you understood them.
        8. When your answer draws on a specific page, article, project, or video, always include its link at the end so the user can read more (e.g. 'You can read more here: <url>'). If a URL is provided in the context for the content you used, you SHOULD include it — don't omit it. Only use URLs that actually appear in the context or known facts; never invent or guess a
        9. Where it feels natural, end with a light, relevant next step — such as reading a related article, downloading the 'It's All About Brand' eBook, viewing an example, or having a chat with the team. Keep it optional and low-pressure; do NOT add a call-to-action to every answer or sound pushy.\n\n";
        $knowledgeBase = "## Knowledge base\n\n"
            . "EntityType: $entityType\n\n"
            . "Entity name: $entityName\n\n"
            . "Related Articles: $relatedArticles\n\n"
            . "Context:\n$context\n\n"
            ."url: https://ocular.nz$url\n\n"
            . "Tags: $tags";



        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt . $knowledgeBase]],
            $history,
            [['role' => 'user', 'content' => $question]]
        );

        $fullText = $systemPrompt . $knowledgeBase . json_encode($history) . $question;
error_log('[ChatService] approx tokens: ' . (int)(strlen($fullText) / 4));

        //step 4: Send to LLM and return the answer
        $answer = $this->chat->generateChat($messages);
        return $answer;
        } catch (\Throwable $e) {
            error_log('[ChatService] ERROR: ' . $e->getMessage());
            error_log('[ChatService] Trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
}
