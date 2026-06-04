<?php
// require_once 'vendor/autoload.php';
// $crawler = new \Ocular\Chatbot\Crawler\ProjectsCrawler();
// echo "\n=== Chunks ===\n";
// $chunks = $crawler->buildChunks();
// echo "Found " . count($chunks) . " chunks\n\n";
// print_r($chunks);


// require_once 'vendor/autoload.php';
// $crawler = new \Ocular\Chatbot\Crawler\AboutUsCrawler();
// echo "=== Team Members ===\n";
// $members = $crawler->getTeamMembers();
// echo "Found " . count($members) . " members\n\n";
// print_r($members);
// echo "\n=== Chunks ===\n";
// $chunks = $crawler->buildChunks();
// echo "Found " . count($chunks) . " chunks\n\n";
// print_r($chunks);

// require_once 'vendor/autoload.php';
// $crawler = new \Ocular\Chatbot\Crawler\ArticlesCrawler();
// $chunks = $crawler->buildChunks();
// echo "Found " . count($chunks) . " chunks\n\n";
// print_r($chunks);


require_once 'vendor/autoload.php';
$crawler = new \Ocular\Chatbot\Crawler\ServiceCrawler();
echo "\n=== Chunks ===\n";
$chunks = $crawler->buildChunks();
echo "Found " . count($chunks) . " chunks\n\n";
print_r($chunks);