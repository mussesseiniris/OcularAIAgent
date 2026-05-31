<?php

declare(strict_types=1);

namespace Ocular\Chatbot\Crawler;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class ServiceCrawler
{
    private Client $client;

    private array $serviceToServiceTypeMap = [
    'Digital Platforms'       => 'Platforms',
    'Content & Communication' => 'Communication',
    'UX & Experience Design'  => 'Experiences',
    'Systems & Integration'   => 'Platforms',
    'Emerging Technology'     => '', 
];

private array $serviceToTagsMap = [
    'Digital Platforms' => [
        'Web Development',
        'Platform Architecture',
        'CRM',
    ],
    'Content & Communication' => [
        'Video',
        'Campaign',
        'Graphic Design',
        'Brand',
    ],
    'UX & Experience Design' => [
        'UX Design',
        'Web Design',
    ],
    'Systems & Integration' => [
        'CRM',
        'Platform Architecture',
    ],
    'Emerging Technology' => [], // no matching tags in knownTags yet
];

    /**
     * Process article URLs scraped directly from the services page.
     * These are the explicit "Our process for X" links Ocular has already made.
     * Stored here so we can bake them into the service chunk metadata at ingest time.
     *
     * These URLs use the tx_news query string pattern on the articles-single page.
     * We derive a stable article ID from the news ID in the URL.
     */
    private array $serviceProcessArticleMap = [
    'UX & Experience Design' => [
        'url'       => '/article/the-design-process-at-ocular/',
        'articleId' => 'article_process_design',
    ],
    'Digital Platforms' => [
        'url'       => '/article/how-we-bring-online-projects-to-life/',
        'articleId' => 'article_process_online',
    ],
    'Systems & Integration' => [
        'url'       => '/article/how-we-bring-online-projects-to-life/',
        'articleId' => 'article_process_online',
    ],
    'Content & Communication' => [
        'url'       => '/article/the-video-process-at-ocular/',
        'articleId' => 'article_process_video',
    ],
    // Emerging Technology omitted — no process article exists
    ];

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://ocular.nz',
            'timeout'  => 10,
        ]);
    }

    /**
     * Scrapes the services page and returns structured data per service.
     *
     * Page structure observed:
     * - Each service is introduced by an h2 heading (Design, Online, Video)
     * - Followed by an h3 tagline
     * - Followed by paragraphs describing the service
     * - Followed by two links: case studies and process article
     *
     * We walk h2 and p tags sequentially, grouping paragraphs under
     * the current service heading until the next h2 is encountered.
     *
     * @return array List of services with name, description, serviceType, tags, processArticle
     */
    public function getServices(): array
    {
        $html    = $this->client->get('/services/')->getBody()->getContents();
        $crawler = new Crawler($html);
        $services = [];

        $currentService     = '';
        $currentParagraphs  = [];
        $currentTagline     = '';

        $crawler->filter('h2, h3, p')->each(function (Crawler $node) use (&$services, &$currentService, &$currentParagraphs, &$currentTagline) {
            $tag  = $node->nodeName();
            $text = trim($node->text());

            // h2 = new service section — save previous service before starting new one
            if ($tag === 'h2' && isset($this->serviceToServiceTypeMap[$text])) {
                if (!empty($currentService) && !empty($currentParagraphs)) {
                    $services[$currentService] = $this->buildServiceEntry(
                        $currentService,
                        $currentTagline,
                        $currentParagraphs
                    );
                }

                $currentService    = $text;
                $currentParagraphs = [];
                $currentTagline    = '';
                return;
            }

            // h3 immediately after h2 = service tagline
            if ($tag === 'h3' && !empty($currentService) && empty($currentTagline)) {
                $currentTagline = $text;
                return;
            }

            // p tags = service description paragraphs
            // Skip very short strings (link text like "Design case studies")
            if ($tag === 'p' && !empty($currentService) && strlen($text) > 40) {
                $currentParagraphs[] = $text;
            }
        });

        // Save the last service after loop ends
        if (!empty($currentService) && !empty($currentParagraphs)) {
            $services[$currentService] = $this->buildServiceEntry(
                $currentService,
                $currentTagline,
                $currentParagraphs
            );
        }

        return array_values($services);
    }

    /**
     * Assembles a single service entry array from its scraped parts.
     */
    private function buildServiceEntry(string $name, string $tagline, array $paragraphs): array
    {
        return [
            'name'           => $name,
            'tagline'        => $tagline,
            'description'    => implode("\n\n", $paragraphs),
            'serviceType'    => $this->serviceToServiceTypeMap[$name],
            'tags'           => $this->serviceToTagsMap[$name] ?? [],
            'processArticle' => $this->serviceProcessArticleMap[$name] ?? null,
            'url'            => '/services/',
        ];
    }

    /**
     * Builds chunks for ingestion. Produces two chunk types per service:
     *
     * - service_overview: tagline + description — answers "What does Ocular offer for branding?"
     * - service_process: links to the process article via relatedArticles metadata field
     *   This is the Option B explicit link — baked in at ingest time.
     *   ChatService can use relatedArticles to fetch the process article chunk directly.
     *
     * serviceTypes and tags use the same vocabulary as projects so the existing
     * ChatService filter detection routes queries correctly without code changes.
     *
     * @return array List of chunks with 'content' and 'metadata'
     */
    public function buildChunks(): array
    {
        $chunks   = [];
        $services = $this->getServices();

        foreach ($services as $service) {
            $content = "{$service['name']}: {$service['tagline']}\n\n{$service['description']}";

            // Chunk 1: Service overview with explicit process article link (Option B)
            $chunks[] = [
                'content'  => $content,
                'metadata' => [
                    'name'            => $service['name'],
                    'entityType'      => 'service',
                    'entityId'        => 'service_' . strtolower($service['name']),
                    'entityName'      => $service['name'],
                    'chunkType'       => 'service_overview',
                    'serviceTypes'    => [$service['serviceType']],
                    'tags'            => $service['tags'],
                    // Option B: explicit article ID baked in — ChatService fetchByIds() uses this
                    'relatedArticles' => $service['processArticle']
                        ? [$service['processArticle']['articleId']]
                        : [],
                    'url'             => $service['url'],
                    'chunk_type'      => 'services',
                    'articleTypes'    => [],
                ],
            ];
        }

        return $chunks;
    }
}