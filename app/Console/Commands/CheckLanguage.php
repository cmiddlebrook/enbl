<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\LinkSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


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
        $sites = $this->getSitesToCheck(); 
        foreach ($sites as $linkSite)
        {
            $domain = $linkSite->domain;
            $this->info("Checking language of {$domain}...");
            $data = $this->makeAPICall($domain);
            sleep(0.05);
            if (!$data) continue;

            $linkSite->country_code = $data['country'] ?? null;

            echo "{$domain} updated! Country Code: $linkSite->country_code \n";
            $linkSite->save();

        }

        $this->withdrawNonEnglishSites();
    }

    private function getSitesToCheck($num = 300)
    {
        $sites = LinkSite::where(function ($query)
        {
            $query->whereNull('country_code')
                ->orWhere('country_code', '');
        })
        ->where('is_withdrawn', 0)
        ->orderBy('semrush_AS', 'desc')
        ->orderBy('majestic_trust_flow', 'desc')
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

    private function withdrawNonEnglishSites()
    {
        $this->info('Withdrawing any new Non English sites...');
        
        DB::table('link_sites')
            ->whereNotNull('country_code')
            ->whereNotIn('country_code', ['US', 'CA', 'GB', 'AU', 'NZ'])
            ->update([
                'is_withdrawn' => 1,
                'withdrawn_reason' => 'language'
            ]);
    }
}
