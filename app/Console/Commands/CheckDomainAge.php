<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\LinkSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class CheckDomainAge extends Command
{
    protected $signature = 'check-domain-age';
    protected $description = 'Checks the age of the domain of some link sites';

    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();
    }

    public function handle()
    {
        $sites = $this->getSitesToCheck(100); 
        echo $sites->count() . " sites to be checked\n";
        foreach ($sites as $linkSite)
        {
            $domain = $linkSite->domain;
            $this->info("Checking age of {$domain}...");
            $data = $this->makeAPICall($domain);
            sleep(0.01);
            if (!$data) continue;

            $creationDate = $data['data']['created_date'] ?? null;
            $linkSite->domain_creation_date = $creationDate ? Carbon::parse($creationDate)->toDateString() : null;
            echo "{$domain} updated! Domain age: $linkSite->domain_creation_date \n";
            $linkSite->save();

        }
    }

    private function getSitesToCheck($num = 500)
    {
        $sites = LinkSite::withAvgLowPrices()
            ->where(function ($query)
            {
                $query->where('last_checked', '<', Carbon::now()->subWeek())
                    ->orWhereNull('last_checked');
            })
            ->where('is_withdrawn', 0)
            ->whereNull('domain_creation_date')
            ->has('sellers', '>=', 3)
            ->where('avg_low_price', '<=', 100)
            ->where('semrush_AS', '>=', 5)
            ->orderBy('avg_low_price', 'asc')
            ->orderBy('majestic_trust_flow', 'desc')
            ->orderBy('semrush_AS', 'desc')
            ->limit($num)
            ->get();

        return $sites;
    }


    private function makeAPICall($domain)
    {
        try
        {
            $response = $this->client->request('GET', "https://domain-age-checker2.p.rapidapi.com/domain-age?url={$domain}", [
                'headers' => [
                    'X-RapidAPI-Host' => 'domain-age-checker2.p.rapidapi.com',
                    'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
                ],
            ]);

            $body = $response->getBody();
            $data = json_decode($body, true);
            // \Symfony\Component\VarDumper\VarDumper::dump($data);

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
