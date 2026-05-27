<?php
require_once 'vendor/autoload.php';
$crawler = new \Ocular\Chatbot\Crawler\OcularCrawler();
$projects = $crawler->getProjectList();
// print_r($projects);
// $projectDetails = $crawler->getProjectDetail('/project/light-house-cinema/');
$testResult = $crawler->buildChunks();
print_r($testResult);
