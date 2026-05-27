<?php

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use Ocular\Chatbot\Service\ChatService;

require 'vendor/autoload.php';

$voyageApi = getenv('VOYAGE_AI_API_KEY');
$groq = getenv('GROQ_API_KEY');

// Set up dependencies
$config = new \Qdrant\Config('qdrant',6333);
$embeddingGenerator = new \Ocular\Chatbot\Embeddings\Voyage4EmbeddingGenerator();
$qdrantVectorStore = new \LLPhant\Embeddings\VectorStores\Qdrant\QdrantVectorStore($config,'ocular_chunks');

$openAIConfig = new \LLPhant\OpenAIConfig();
$openAIConfig->apiKey = $groq;
$openAIConfig->url = 'https://api.groq.com/openai/v1/';
$openAIConfig->model = 'llama-3.1-8b-instant';
$chat = new \LLPhant\Chat\OpenAIChat($openAIConfig);

// Instantiate ChatService
$chatService = new \Ocular\Chatbot\Service\ChatService($embeddingGenerator, $qdrantVectorStore, $chat);

// Test ask()
$answer = $chatService->ask('What brand projects has Ocular done?');
echo $answer;