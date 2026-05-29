<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
// $dotenv->load();

use Ocular\Chatbot\Crawler\OcularCrawler;
use Ocular\Chatbot\Crawler\AboutUsCrawler;
use Ocular\Chatbot\Domain\Model\ChunkDocument;
use Ocular\Chatbot\Embeddings\Voyage4EmbeddingGenerator;
use Ocular\Chatbot\Service\QdrantIngester;
use Qdrant\Config;
use Qdrant\Http\Transport;
use Http\Discovery\Psr18ClientDiscovery;



echo "Starting ingestion...\n";

//Crawl and build chunks
echo "Crawling ocular.nz...\n";
$crawler = new OcularCrawler();
// $crawler = new AboutUsCrawler();
$chunks = $crawler->buildChunks();
echo "Found " . count($chunks) . " chunks\n";

//Set up Voyage AI embedding generator
$embeddingGenerator = new Voyage4EmbeddingGenerator();
echo "API Key: " . substr(getenv('VOYAGE_AI_API_KEY'), 0, 10) . "...\n";

// Test Voyage AI connection
echo "Testing Voyage AI connection...\n";
$testEmbedding = $embeddingGenerator->embedText("test");
echo "Embedding length: " . count($testEmbedding) . "\n";
if (empty($testEmbedding)) {
    die("ERROR: Voyage AI returned empty embedding. Check your API key.\n");
}

//Set up Qdrant ingester
$config = new Config('qdrant', 6333);
$qdrantIngester = new QdrantIngester(
    $config,
    'ocular_chunks',
    null
);

//Loop through chunks, embed and ingest
foreach ($chunks as $index => $chunk) {
    echo "Processing chunk " . ($index + 1) . " of " . count($chunks) . ": " . $chunk['metadata']['name'] . " (" . $chunk['metadata']['chunk_type'] . ")\n";

    // Create ChunkDocument
    $doc = new ChunkDocument();
    $doc->content = $chunk['content'];
    $doc->embeddingText = $chunk['content'];
    $doc->chunkId = 'chunk_' . strtolower(str_replace(' ', '_', $chunk['metadata']['entityId'])) . '_' . $chunk['metadata']['chunk_type'];
    $doc->entityId = $chunk['metadata']['entityId'] . strtolower(str_replace(' ', '_', $chunk['metadata']['name']));
    $doc->entityType = $chunk['metadata']['entityType'];
    $doc->entityName = $chunk['metadata']['name'];
    $doc->serviceTypes = $chunk['metadata']['serviceTypes'];
    $doc->tags = $chunk['metadata']['tags'];
    $doc->chunkType = $chunk['metadata']['chunk_type'];
    $doc->sourceName = $chunk['metadata']['url'];

    if (empty(trim($doc->content))) {
    echo "SKIPPING: Empty content for chunk: " . $doc->chunkId . "\n";
    continue;
    }   

    // Embed
    $doc = $embeddingGenerator->embedDocument($doc);
    // Small delay to avoid rate limiting
    usleep(1000000);

    if (empty($doc->embedding)) {
    echo "ERROR: Empty embedding for chunk: " . $doc->chunkId . "\n";
    echo "Content: " . substr($doc->content, 0, 100) . "\n";
    die();
    }
    // Ingest into Qdrant
    $qdrantIngester->addDocument($doc);

    echo "Ingested: " . $doc->chunkId . "\n";
}

echo "\nDone! " . count($chunks) . " chunks ingested into Qdrant.\n";