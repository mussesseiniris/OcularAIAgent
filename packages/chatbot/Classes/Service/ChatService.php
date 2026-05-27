<?php

namespace Ocular\Chatbot\Service;

use Doctrine\DBAL\Schema\UniqueConstraint;
use Ocular\Chatbot\Embeddings\Voyage4EmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\Qdrant\QdrantVectorStore;
use LLPhant\Chat\OpenAIChat;
use Qdrant\Models\Filter\Condition\MatchAny;
use Qdrant\Models\Filter\Filter;


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
        $searchRequest = (new \Qdrant\Models\Request\Points\QueryRequest())
            ->setQuery(['nearest' => $questionEmbedding])
            ->setUsing('openai')
            ->setLimit($limit)
            ->setWithPayload(true);

        $synonymServiceTypeMap = [
            // 'website development' => 'Digital Platforms',
            // 'web development' => 'Digital Platforms',
            // 'platform' => 'Digital Platforms',
            // 'ux' => 'UX & Experience Design',
            // 'experience' => 'UX & Experience Design',
            // 'design' => 'UX & Experience Design', 
            // 'graphy' => 'Content & Communication',
            // 'brand' => 'Content & Communication',
            // 'branding' => 'Content & Communication',
            // 'video' => 'Content & Communication',
            // 'intergration' => 'Systems & Integration',
            // 'ai' => 'Emerging Technology',
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
            // 'Digital Platforms',
            // 'UX & Experience Design',
            // 'Content & Communication',
            // 'Systems & Integration',
            // 'Emerging Technology'
            'Platforms',
            'Communication',
            'Experiences',
        ];

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
        $detectedServiceTypes = array_unique($detectedServiceTypes);


        $synonymTagsMap = [
            'brand' => 'Brand',
            'branding' => 'Brand',
            'UX' => 'UX Design',
            'video design' => 'Video',
            'graphy' => 'Graphic Design',
            'photo' => 'Graphic Design',
            'web' => 'Web Development',
            'website' => 'Web Development',
            'website design' => 'Web Design',
            'event' => 'Campaign',
            'customer relationship management' => 'CRM',
            'platform' => 'Platform Architecture',
        ];
        $knownTags = [
            'Video',
            'UX Design',
            'Brand',
            'Graphic Design',
            'Web Development',
            'Web Design',
            'Campaign',
            'CRM',
            'Platform Architecture',
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
        $detectedTags = array_unique($detectedTags);

        $filter = new Filter();
        if (!empty($detectedServiceTypes)) {
            $filter->addMust(new MatchAny('service_types', $detectedServiceTypes));
        }
        if (!empty($detectedTags)) {

            $filter->addMust(new MatchAny('tags', $detectedTags));
        }

        if (!empty($detectedServiceTypes) || !empty($detectedTags)) {
            $searchRequest->setFilter($filter);
        }

        $response = $this->qdrantVectorStore->client
            ->collections('ocular_chunks')
            ->points()
            ->query()
            ->query($searchRequest);


        return $response->__toArray()['result']['points'];
    }

    public function ask(string $question): string
    {
        //step 1: Get relevant chunks from vetor databasde(Qdrant)
        $results = $this->search($question);
        //step 2: Build context from chunks
        $entityType = implode("\n\n", array_map(fn($doc) => $doc['payload']['entity_type'], $results));
        $entityName= implode("\n\n", array_map(fn($doc) => $doc['payload']['entity_name'], $results));
        $context = implode("\n\n", array_map(fn($doc) => $doc['payload']['content'], $results));
        //step 3: Build prompt 
        $prompt = "Using the following context to answer questions. \n\nEntityType:$entityType\n\nEntity name:$entityName\n\nContext:\n$context;\n\nQuestion:$question.";
        //step 4: Send to LLM and return the answer
        $answer = $this->chat->generateText($prompt);
        return $answer;
    }
}
