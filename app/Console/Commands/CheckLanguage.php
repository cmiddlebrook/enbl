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
        $sites = $this->getSitesToCheck(5);
        foreach ($sites as $linkSite)
        {
            $domain = $linkSite->domain;
            $this->info("Checking language of {$domain}...");
            $data = $this->makeAPICall($domain);
            if (!$data) continue;

            $linkSite->country_code = $data['country'] ?? null;

            echo "{$domain} updated! Country Code: $linkSite->country_code \n";
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
        try
        {
            $response = $this->client->request('GET', "https://domain-validation1.p.rapidapi.com/getDomainCountry?domain={$domain}", [
                'headers' => [
                    'X-RapidAPI-Host' => 'domain-validation1.p.rapidapi.com',
                    'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
                ],
            ]);

            $body = $response->getBody();
            $data = json_decode($body, true);
            // \Symfony\Component\VarDumper\VarDumper::dump($response);

            return $data;
        }
        catch (\GuzzleHttp\Exception\ClientException $e)
        {
            echo $e->getMessage();
            return false;
        }
    }
}
