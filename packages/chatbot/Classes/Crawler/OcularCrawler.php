<?php

namespace Ocular\Chatbot\Crawler;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class OcularCrawler
{
    private Client $client;
    private array $knownServiceTypes;

    public function __construct()
    {
        $this->client = new Client(['timeout' => 10]);
        $this->knownServiceTypes = ['Platforms', 'Communication', 'Experiences'];
    }

    public function getProjectList(): array
    {

        $projects = [];
        $html = $this->client->get('/projects/')->getBody()->getContents();
        $knownServiceTypes = $this->knownServiceTypes;
        $crawler = new Crawler($html);
        $crawler->filter('div.project-wrap')->each(function (Crawler $node) use (&$projects,$knownServiceTypes) {
            $url = $node->filter('a')->attr('href');
            $dataGroups = json_decode($node->attr('data-groups'), true);
            $name = $node->filter('a')->attr('title');
            $serviceTypes=[];
            $tags=[];
            foreach($dataGroups as $group){
                if(in_array($group,$knownServiceTypes)){
                    $serviceTypes[]=$group;
                }
                else{$tags[] = $group;}
            }

            $projects[] = [
                'url' => $url,
                'name' => $name,
                'tag' => $tags,
                'serviceTypes' => $serviceTypes,
            ];
        });
        return $projects;
    }
}
