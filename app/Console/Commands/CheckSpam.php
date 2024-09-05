<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\LinkSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class CheckSpam extends Command
{
    protected $signature = 'check-spam';
    protected $description = 'Withdraws domain names with spam words in them';

    protected $spamWords;
    protected $options;
    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();

        $this->options = ['y', 'n'];
        $this->spamWords = array(
            'porn',
            'xxx',
            'india',
            'hindi',
            'hindu',
            'dubai',
            'torrent',
            'cannabis',
            'cbd',
            'pharma',
            'cialis',
            'viagra',
            'casino',
            'poker',
            'roulette',
            'bingo',
            'gambling',
            'betting',
            'crypto',
            'bitcoin',
            'coinbase',
            'forex',
            'insurance',
            'outlet',
            'michaelkors',
            'vuitton',
            'burberry',
            'rayban',
            'my.id'
        );
    }


    public function handle()
    {
        $this->checkDomainNameStrings();
        // $this->checkBlacklist();
    }

    private function checkDomainNameStrings()
    {        
        $sites = $this->getAllLiveSites();
        foreach ($sites as $linkSite)
        {
            $domain = $linkSite->domain;
            // $this->info("Parsing Domain {$domain}");

            foreach ($this->spamWords as $spamWord)
            {
                if (Str::contains($domain, $spamWord))
                {
                    $this->promptUserForDomainWithdrawl($domain);
                }
            }            
        }
    }

    private function checkBlacklist()
    {
        $sites = $this->getSitesToCheck(1); 
        echo $sites->count() . " sites to be checked\n";
        foreach ($sites as $linkSite)
        {
            $domain = $linkSite->domain;
            $ip = $linkSite->ip_address;
            $this->info("Checking {$domain} with {$ip} for spam reputaion");
            $data = $this->makeAPICall($ip);
            // \Symfony\Component\VarDumper\VarDumper::dump($data);
            if (!$data) break;

            if (true)
            {
                // echo "{$domain} updated! Domain age: $linkSite->domain_creation_date \n";
                $linkSite->save();    
            }
        }
    }

    private function promptUserForDomainWithdrawl($domain)
    {
        $action = $this->choice("Withdraw {$domain}?", $this->options, $defaultIndex = 0);
        echo $action;
        if (strtolower($action) === 'y')
        {
            $this->withdrawDomain($domain);            
            return true;
        }

        return false;
    }

    private function withdrawDomain($domain)
    {
        DB::table('link_sites')
        ->where('domain', '=', $domain)
        ->update([
            'is_withdrawn' => 1,
            'withdrawn_reason' => 'spam'
        ]);
        echo "{$domain} withdrawn\n";
    }

    private function getAllLiveSites()
    {
        $sites = LinkSite::where('is_withdrawn', 0)
            ->orderBy('semrush_AS', 'desc')
            ->get();

        return $sites;
    }

    private function getSitesToCheck($num = 100)
    {
        $sites = LinkSite::withAvgLowPrices()->withLowestPrice()
            ->where('is_withdrawn', 0)
            ->whereNotNull('ip_address')
            ->has('sellers', '>=', 2)
            ->where('avg_low_price', '<=', 25)
            ->where('semrush_AS', '>=', 10)
            ->orderBy('avg_low_price', 'asc')
            ->orderBy('majestic_trust_flow', 'desc')
            ->orderBy('semrush_AS', 'desc')
            ->limit($num)
            ->get();

        return $sites;
    }

    private function makeAPICall($ip)
    {
        // try
        // {
        //     $response = $this->client->request('GET', "https://ip-blacklist-lookup-api-apiverve.p.rapidapi.com/v1/ipblacklistlookup?ip={$ip}", [
        //         'headers' => [
        //             'X-RapidAPI-Host' => 'ip-blacklist-lookup-api-apiverve.p.rapidapi.com',
        //             'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
        //         ],
        //     ]);

        //     $body = $response->getBody();
        //     $data = json_decode($body, true);
        //     \Symfony\Component\VarDumper\VarDumper::dump($data);

        //     return $data;
        // }
        // catch (\GuzzleHttp\Exception\ClientException $e)
        // {
        //     $errorMessage = $e->getMessage();
        //     if (strpos($errorMessage, "429 Too Many Requests"))
        //     {
        //         echo "Daily API quota reached\n";
        //         exit;
        //     }
        //     echo $errorMessage;

        //     return false;
        // }
    }
}
