<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\LinkSite;
use Illuminate\Console\Command;


class CheckLanguage extends Command
{
    protected $signature = 'check-language';
    protected $description = 'Checks the language and country code of some link sites';

    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();
    }

    public function handle()
    {
        $this->info('Starting to check language...');
        
        $sites = $this->getSitesToCheck();
        foreach ($sites as $linkSite)
        {
            $domain = $linkSite->domain;
            $data = $this->makeAPICall($domain);

            $linkSite->country_code = $data['country_code'] ?? null;
            $linkSite->page_lang = $data['page_lang'] ?? null;

            echo "{$domain} updated! Country Code: $linkSite->country_code, Page Language: $linkSite->page_lang \n";
            // \Symfony\Component\VarDumper\VarDumper::dump($data);
            $linkSite->save();
    
        }
    }

    private function getSitesToCheck($num = 100)
    {
        $sites = LinkSite::whereNull('country_code')
            ->orWhere('country_code', '')
            ->limit($num)
            ->get();

        return $sites;
    }

    private function makeAPICall($domain)
    {
        $response = $this->client->request('GET', "https://domain-rank-language-country-iab-category.p.rapidapi.com/domain.php?domain={$domain}", [
            'headers' => [
                'X-RapidAPI-Host' => 'domain-rank-language-country-iab-category.p.rapidapi.com',
                'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
            ],
        ]);

        $body = $response->getBody();
        $data = json_decode($body, true);

        return $data;
    }
}
