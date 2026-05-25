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
        $this->client = new Client([
            'base_uri' => 'https://ocular.nz',
            'timeout' => 10
        ]);
        $this->knownServiceTypes = ['Platforms', 'Communication', 'Experiences'];
    }

    //scrape from the website to create metadata
    public function getProjectList(): array
    {
        $projects = [];
        $html = $this->client->get('/projects/')->getBody()->getContents();
        $knownServiceTypes = $this->knownServiceTypes;
        $crawler = new Crawler($html);
        $crawler->filter('div.project-wrap')->each(function (Crawler $node) use (&$projects, $knownServiceTypes) {
            $url = $node->filter('a')->attr('href');
            $dataGroups = json_decode($node->attr('data-groups'), true);
            $name = $node->filter('a')->attr('title');
            $serviceTypes = [];
            $tags = [];
            foreach ($dataGroups as $group) {
                if (in_array($group, $knownServiceTypes)) {
                    $serviceTypes[] = $group;
                } else {
                    $tags[] = $group;
                }
            }

            $projects[] = [
                'url' => $url,
                'name' => $name,
                'tags' => $tags,
                'serviceTypes' => $serviceTypes,
            ];
        });
        return $projects;
    }

    public function getProjectDetail(string $url): array
    {

        $html = $this->client->get($url)->getBody()->getContents();
        $crawler = new Crawler($html);
        $description = $crawler->filter('meta[name="description"]')->attr('content');
        $details = $crawler->filter('div.ce-bodytext p')->each(function (Crawler $node) {
            return trim($node->text());
        });
        $details = array_filter($details);
        $details = array_values($details);
        $details = array_slice($details, 1, -1);
        $detail = implode("\n\n", array_filter($details));
        return [
            'description' => $description,
            'detail' => $detail,
        ];
    }

    public function buildChunks(): array
    {
        $contents = [];
        $projects = $this->getProjectList();
        foreach ($projects as $project) {
            $projectDetails = $this->getProjectDetail($project['url']);
            $contents[] = [
                'content' => $projectDetails['description'],
                'metadata' => [
                    'name' => $project['name'],
                    'chunk_type' => 'description',
                    'url' => $project['url'],
                    'tags' => $project['tags'],
                    'serviceTypes' => $project['serviceTypes'],
                ],
            ];
            $contents[] = [
                'content' => $projectDetails['detail'],
                'metadata' => [
                    'name' => $project['name'],
                    'chunk_type' => 'detail',
                    'url' => $project['url'],
                    'tags' => $project['tags'],
                    'serviceTypes' => $project['serviceTypes'],
                ]
            ];
        }
        return $contents;
    }
}
