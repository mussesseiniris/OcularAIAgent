<?php
// require_once 'vendor/autoload.php';
// $crawler = new \Ocular\Chatbot\Crawler\OcularCrawler();
// $projects = $crawler->getProjectList();
// // print_r($projects);
// // $projectDetails = $crawler->getProjectDetail('/project/light-house-cinema/');
// $testResult = $crawler->buildChunks();
// print_r($testResult);


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

require_once 'vendor/autoload.php';
$crawler = new \Ocular\Chatbot\Crawler\ArticlesCrawler();
// $projects = $crawler->getProjectList();
// print_r($projects);
// $projectDetails = $crawler->getProjectDetail('/project/light-house-cinema/');
echo "\n=== Chunks ===\n";
$chunks = $crawler->buildChunks();
echo "Found " . count($chunks) . " chunks\n\n";
print_r($chunks);


// require_once 'vendor/autoload.php';
// $crawler = new \Ocular\Chatbot\Crawler\ServiceCrawler();
// // $projects = $crawler->getProjectList();
// // print_r($projects);
// // $projectDetails = $crawler->getProjectDetail('/project/light-house-cinema/');
// echo "\n=== Chunks ===\n";
// $chunks = $crawler->buildChunks();
// echo "Found " . count($chunks) . " chunks\n\n";
// print_r($chunks);