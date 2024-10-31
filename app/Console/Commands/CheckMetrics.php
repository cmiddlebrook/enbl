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

        $this->checkPricingBand(15, 20, 1, 10); // $50 band
        $this->checkPricingBand(30, 40, 2, 15); // $100 band
        $this->checkPricingBand(55, 70, 3, 20); // $175 band
        $this->checkPricingBand(90, 110, 4, 25); // $275 band
        $this->checkPricingBand(200, 500, 50, 5); // more checks

        echo "{$this->numApiCalls} API calls made\n";
    }

    private function checkPricingBand($bandMaxPrice, $max4thLowPrice, $priceIncrement, $minSEMRushAS)
    {
        if ($this->numApiCalls >= $this->maxApiCalls) return;

        echo "Checking Pricing Band with max \${$bandMaxPrice} and max 4th lowest price \${$max4thLowPrice}\n";

        // first do some broad checks at lower price points
        $jump = floor($bandMaxPrice / 3);
        for ($startPrice = 5; $startPrice < $bandMaxPrice;)
        {
            $low3rdThisRun = min(floor($startPrice * 1.5), $max4thLowPrice);
            $this->checkSites(4, $startPrice, $low3rdThisRun, $minSEMRushAS);
            if ($this->numApiCalls >= $this->maxApiCalls) break;

            $startPrice += $jump;
            $startPrice = min($startPrice, $bandMaxPrice);
        }

        // then check at the max price point for this band but with higher averages
        for ($thirdLowestPrice = $bandMaxPrice + $priceIncrement; $thirdLowestPrice <= $max4thLowPrice; $thirdLowestPrice += $priceIncrement)
        {
            $this->checkSites(4, $bandMaxPrice, $thirdLowestPrice, $minSEMRushAS);
            if ($this->numApiCalls >= $this->maxApiCalls) break;
        }
    }

    private function checkSites($numSellers, $lowestPrice, $max4thLowPrice, $minSRAS)
    {
        $sites = $this->getSitesToCheck($numSellers, $lowestPrice, $max4thLowPrice, $minSRAS);
        $numSites = $sites->count();
        echo "Checking {$numSites} with {$numSellers} sellers, lowest price: {$lowestPrice}, 4th lowest: {$max4thLowPrice}\n";

        foreach ($sites as $linkSite)
        {
            $domain = $linkSite->domain;

            $this->info("Checking {$domain}");
            $data = $this->makeAPICall($domain);
            // \Symfony\Component\VarDumper\VarDumper::dump($data);
            if (!$data) continue;

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

            if ($this->numApiCalls >= $this->maxApiCalls) break;
        }
    }

    private function getSitesToCheck($numSellers, $lowestPrice, $max4thLowPrice, $minSRAS)
    {
        // $sites = LinkSite::where('is_withdrawn', 0)
        //     // ->where(function ($query)
        //     // {
        //     //     $query->where('last_checked_mozmaj', '<', Carbon::now()->subMonth())
        //     //         ->orWhereNull('last_checked_mozmaj');
        //     // })
        //     ->whereNull('last_checked_mozmaj') // remove this once we have filled in the gaps
        //     ->whereNotNull('semrush_traffic')
        //     ->has('sellers', '>=', $numSellers)
        //     ->where('lowest_price', '<=', $lowestPrice)
        //     ->having('fourth_lowest_price', '<=', $max4thLowPrice)
        //     ->where('semrush_AS', '>=', $minSRAS)
        //     // ->orderBy('last_checked_mozmaj', 'asc')
        //     ->orderBy('semrush_organic_kw', 'desc')
        //     ->orderBy('majestic_trust_flow', 'desc')
        //     ->orderBy('semrush_AS', 'desc')
        //     // ->limit(100)
        //     ->get();


        $sites = LinkSite::select('link_sites.*', 'p.lowest_price', 'p.fourth_lowest_price', 'p.price_difference_percentage')
        ->join('link_site_with_prices as p', 'link_sites.id', '=', 'p.link_site_id')
        ->where('link_sites.is_withdrawn', 0)
        ->whereNull('link_sites.last_checked_mozmaj')
        ->whereNotNull('link_sites.semrush_traffic')
        ->has('sellers', '>=', $numSellers)
        ->where('p.lowest_price', '<=', $lowestPrice)
        ->having('p.fourth_lowest_price', '<=', $max4thLowPrice)
        ->where('link_sites.semrush_AS', '>=', $minSRAS)
        ->orderByDesc('link_sites.semrush_organic_kw')
        ->orderByDesc('link_sites.majestic_trust_flow')
        ->orderByDesc('link_sites.semrush_AS')
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
