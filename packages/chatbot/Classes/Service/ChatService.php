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

    public function search(string $question, int $limit = 10): array
    {
        // Generate embedding for the question
        $questionEmbedding = $this->voyage4EmbeddingGenerator->embedText($question);

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
        Frame creativity and design as tools for solving problems, not as standalone achievements.\n\n"
            . "## Known facts about Ocular
        Contact email: results@ocular.nz
        Website: https://ocular.nz
        (When users ask how to contact Ocular, provide the email AND tell them they can use the Contact Us form at https://ocular.nz)\n\n
        'It's All About Brand' eBook download:https://ocular.nz/article/its-all-about-brand-free-ebook/"
            . "## How to answer
        0. If the user is just greeting you (e.g. 'hi', 'hello', 'thanks') or making small talk, respond naturally and briefly, and invite them to ask a question about Ocular. This does NOT require any knowledge base content.
        0.5. If the user asks a follow-up question that refers to something already discussed (e.g. uses words like 'that', 'it', 'those', 'the project', 'what about it'), answer ONLY from the conversation history above. Do NOT use the knowledge base content below for such questions, as it may be about a different topic entirely.
        1. First, judge whether the provided context actually addresses the user's question.
        2. If the context directly and clearly answers the question, respond directly and confidently using that content.
        3. If the context is NOT relevant to the question, or only loosely related, first check whether the conversation history already contains enough information to answer. If yes, use the history to answer. If neither the context nor the history can answer it, reply exactly: \"Sorry, I don't have information about that. Please contact us or check the Ocular website for more details.\"
        4. Never mix unrelated topics. For example, a question about pricing must NOT be answered using content about wages, salaries, or unrelated articles just because they share a similar word.
        5. Only state facts that are explicitly supported by the context below.
        6. Never start your answer with phrases like \"Based on the provided context\", \"According to the context\", or similar. Just answer directly.\n\n";
        $knowledgeBase = "## Knowledge base\n\n"
            . "EntityType: $entityType\n\n"
            . "Entity name: $entityName\n\n"
            . "Related Articles: $relatedArticles\n\n"
            . "Context:\n$context\n\n"
            . "Tags: $tags";

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt . $knowledgeBase]],
            $history,
            [['role' => 'user', 'content' => $question]]
        );

        //         //step 3: Build prompt 
        //         $prompt =
        //             "## Role & Purpose
        // You are a website support chatbot for Ocular. 
        // Answer visitor questions using ONLY the knowledge base content provided below. 
        // Do not use outside knowledge or make assumptions beyond the indexed content.\n\n"
        //     . "## Known facts about Ocular
        // Contact email: results@ocular.nz
        // (Use this email when users ask how to contact Ocular.)\n\n"
        //             . "## How to answer
        // 0. If the user is just greeting you (e.g. 'hi', 'hello', 'thanks') or making small talk, respond naturally and briefly, and invite them to ask a question about Ocular. This does NOT require any knowledge base content.
        // 1. First, judge whether the provided context actually addresses the user's question.
        // 2. If the context directly and clearly answers the question, respond directly and confidently using that content.
        // 3. If the context is NOT relevant to the question, or only loosely related, DO NOT try to force an answer from it. 
        //    In that case, reply exactly: \"Sorry, I don't have information about that. Please contact us or check the Ocular website for more details.\"
        // 4. Never mix unrelated topics. For example, a question about pricing must NOT be answered using content about wages, salaries, or unrelated articles just because they share a similar word.
        // 5. Only state facts that are explicitly supported by the context below.\n\n"

        //             . "## Knowledge base\n\n"
        //             . "EntityType: $entityType\n\n"
        //             . "Entity name: $entityName\n\n"
        //             . "Related Articles: $relatedArticles\n\n"
        //             . "Context:\n$context\n\n"
        //             . "Tags: $tags\n\n"
        //             . "## User question\n$question";

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
