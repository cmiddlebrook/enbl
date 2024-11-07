<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\LinkSite;
use App\Models\LinkSiteWithPrices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class CheckMetrics extends Command
{

    protected $signature = 'check-metrics';
    protected $description = 'Checks the metrics of some of the link sites';

    protected $client;
    protected $numApiCalls = 0;
    protected $maxApiCalls = 100;

    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();
    }

    public function handle()
    {
        $this->info('Starting to check domain metrics...');
        $this->checkSites(4, 25, 10);
        $this->checkSites(4, 25, 2);
        $this->checkSites(4, 100, 10);
        $this->checkSites(4, 500, 2);
        $this->checkSites(3, 500, 2);
        $this->checkSites(2, 500, 2);
        $this->checkSites(1, 500, 2);
        echo "{$this->numApiCalls} API calls made\n";
    }


    private function checkSites($numSellers, $max4thLowPrice, $minSRAS)
    {
        $sites = $this->getSitesToCheck($numSellers, $max4thLowPrice, $minSRAS);
        $numSites = $sites->count();
        echo "Checking {$numSites} with {$numSellers} sellers, max 4th lowest: {$max4thLowPrice}\n";

        foreach ($sites as $linkSite)
        {
            $domain = $linkSite->domain;

            $this->info("Checking {$domain}");
            $data = $this->makeAPICall($domain);
            // \Symfony\Component\VarDumper\VarDumper::dump($data);
            if (!$data) continue;

            if ($this->numApiCalls >= $this->maxApiCalls) return; 
            
            $linkSite->moz_da = $data['mozDA'] ?? null;
            $linkSite->moz_pa = $data['mozPA'] ?? null;
            $linkSite->moz_rank = $data['mozRank'] ?? null;
            $linkSite->moz_links = $data['mozLinks'] ?? null;
            $linkSite->majestic_trust_flow = $data['majesticTF'] ?? null;
            $linkSite->majestic_citation_flow = $data['majesticCF'] ?? null;
            $linkSite->majestic_ref_domains = $data['majesticRefDomains'] ?? null;
            $linkSite->majestic_ref_edu = $data['majesticRefEDU'] ?? null;
            $linkSite->majestic_ref_gov = $data['majesticRefGov'] ?? null;
            $linkSite->majestic_TTF0_name = !empty($data['majesticTTF0Name']) ? $data['majesticTTF0Name'] : null;
            $linkSite->majestic_TTF0_value = !empty($data['majesticTTF0Value']) ? (int)$data['majesticTTF0Value'] : null;
            $linkSite->majestic_TTF1_name = !empty($data['majesticTTF1Name']) ? $data['majesticTTF1Name'] : null;
            $linkSite->majestic_TTF1_value = !empty($data['majesticTTF1Value']) ? (int)$data['majesticTTF1Value'] : null;
            $linkSite->majestic_TTF2_name = !empty($data['majesticTTF2Name']) ? $data['majesticTTF2Name'] : null;
            $linkSite->majestic_TTF2_value = !empty($data['majesticTTF2Value']) ? (int)$data['majesticTTF2Value'] : null;

            $linkSite->facebook_shares = $data['FB_shares'] ?? null;
            $linkSite->last_checked_mozmaj = Carbon::now();

            echo "{$domain} updated!\n";
            $linkSite->save();
        }
    }

    private function getSitesToCheck($numSellers, $max4thLowPrice, $minSRAS)
    {
        $sites = LinkSite::select('link_sites.*', 'p.lowest_price', 'p.fourth_lowest_price', 'p.price_difference_percentage')
            ->join('link_site_with_prices as p', 'link_sites.id', '=', 'p.link_site_id')
            ->where('link_sites.is_withdrawn', 0)
            ->where(function ($query)
            {
                $query->whereNull('link_sites.last_checked_mozmaj')
                    ->orWhere('link_sites.last_checked_mozmaj', '<=', Carbon::now()->subDays(90));
            })
            ->whereNotNull('link_sites.semrush_traffic')
            ->has('sellers', '>=', $numSellers)
            ->where('p.fourth_lowest_price', '<=', $max4thLowPrice)
            ->where('link_sites.semrush_AS', '>=', $minSRAS)
            ->orderBy('link_sites.last_checked_mozmaj')
            ->orderByDesc('link_sites.semrush_organic_kw')
            ->orderByDesc('link_sites.semrush_AS')
            ->orderByDesc('p.fourth_lowest_price')
            ->get();

        return $sites;
    }

    private function makeAPICall($domain)
    {
        try
        {
            ++$this->numApiCalls;
            $response = $this->client->request('GET', "https://domain-metrics-check.p.rapidapi.com/domain-metrics/{$domain}/", [
                'headers' => [
                    'X-RapidAPI-Host' => 'domain-metrics-check.p.rapidapi.com',
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
}
