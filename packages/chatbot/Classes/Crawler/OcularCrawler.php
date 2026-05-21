<?php

namespace Ocular\Chatbot\Crawler;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class OcularCrawler
{
    private Client $client;
    private array $knownServiceTypes;
}
