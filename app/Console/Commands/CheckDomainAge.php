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
        $sites = $this->getSitesToCheck(); 
        echo $sites->count() . " sites to be checked\n";
        foreach ($sites as $linkSite)
        {
            $domain = $linkSite->domain;
            $this->info("Checking age of {$domain}");
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
        $sites = LinkSite::withAvgLowPrices()->withLowestPrice()
            ->where('is_withdrawn', 0)
            ->whereNull('domain_creation_date')
            ->has('sellers', '>=', 2)
            ->where('avg_low_price', '<=', 50)
            ->where('semrush_AS', '>=', 15)
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
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, "429 Too Many Requests"))
            {
                echo "Daily API quota reached\n";
                exit;
            }
            echo $errorMessage;

            return false;
        }
    }

}
