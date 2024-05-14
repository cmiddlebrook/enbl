<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckLanguage extends Command
{

    protected $signature = 'check-language';
    protected $description = 'Checks the language and country code of some link sites';

    public function handle()
    {
        $this->info('Starting to check language...');

        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', 'https://domain-rank-language-country-iab-category.p.rapidapi.com/domain.php?domain=deutschland.de', [
            'headers' => [
                'X-RapidAPI-Host' => 'domain-rank-language-country-iab-category.p.rapidapi.com',
                'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
            ],
        ]);

        $body = $response->getBody();
        $data = json_decode($body, true);
        \Symfony\Component\VarDumper\VarDumper::dump($data);

        // $domain = 'jusebeauty.co.uk';
       
        // $response = Http::withHeaders([
        //     'X-RapidAPI-Host' => 'domain-rank-language-country-iab-category.p.rapidapi.com',
        //     'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
        // ])->get("https://domain-rank-language-country-iab-category.p.rapidapi.com/domain.php?domain={$domain}");            

        // if ($response->successful())
        // {
        //     \Symfony\Component\VarDumper\VarDumper::dump($response->getBody());
        // }
        // else
        // {
        //     $this->error('Failed to fetch language metrics.');
        // }
    }
}
