<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\LinkSite;
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
    protected $maxApiCalls = 50;

    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();
    }

    public function handle()
    {
        // $options = ['Moz & Majestic', 'SEMRush KW & Links', 'Ahrefs'];
        // $action = $this->choice('What metrics would you like to check?', $options, $defaultIndex = 0);

        // $this->info('You have chosen to: ' . $action);

        // switch ($action)
        // {
        //     case 'Moz & Majestic':
        //         $this->info('Updating Moz & Majestic metrics');
        //         break;
        //     case 'SEMRush KW & Links':
        //         $this->info('Updating SEMRush keywords and links');
        //         break;
        //     case 'Ahrefs':
        //         $this->info('Updating Ahrefs metrics');
        //         break;
        //     default:
        //         $this->error('No valid action selected!');
        //         break;
        // }

        $this->info('Starting to check domain metrics...');

        $this->checkPricingBand(10, 13, 5, 5); // $25 band
        $this->checkPricingBand(10, 25, 5, 10); // $50 band
        $this->checkPricingBand(10, 50, 10, 15); // $100 band
        $this->checkPricingBand(15, 85, 15, 20); // $175 band
        $this->checkPricingBand(15, 135, 15, 25); // $275 band
        $this->checkPricingBand(15, 200, 20, 30); // $400 band
        
        echo "{$this->numApiCalls} API calls made\n";
    }

    private function checkPricingBand($bandLowPrice, $bandMaxPrice, $priceIncrement, $minSEMRushAS)
    {
        if ($this->numApiCalls >= $this->maxApiCalls) return;

        echo "Checking Pricing Band from \${$bandLowPrice} to \${$bandMaxPrice}\n";
        for ($startPrice = $bandLowPrice; $startPrice <= $bandMaxPrice; $startPrice += $priceIncrement)
        {
            $lowPrice = $startPrice;            
            for($avgLowPrice = $lowPrice + $priceIncrement; $avgLowPrice <= $startPrice * 2; $avgLowPrice += $priceIncrement)
            {
                $this->checkSites(3, $lowPrice, $avgLowPrice, $minSEMRushAS);
                if ($this->numApiCalls >= $this->maxApiCalls) break;
            }
        }
    }

    private function checkSites($numSellers, $lowestPrice, $avgLowPrice, $minSRAS)
    {
        $sites = $this->getSitesToCheck(100, $numSellers, $lowestPrice, $avgLowPrice, $minSRAS);
        $numSites = $sites->count();
        echo "Checking {$numSites} with {$numSellers} sellers, lowest price: {$lowestPrice}, low average: {$avgLowPrice}\n";

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
            $linkSite->facebook_shares = $data['FB_shares'] ?? null;
            $linkSite->last_checked = Carbon::now();

            echo "{$domain} updated!\n";
            $linkSite->save();    

            if ($this->numApiCalls >= $this->maxApiCalls) break;
        }
    }

    private function getSitesToCheck($num, $numSellers, $lowestPrice, $avgLowPrice, $minSRAS)
    {
        $sites = LinkSite::withAvgLowPrices()->withLowestPrice()
            ->where(function ($query)
            {
                $query->where('last_checked', '<', Carbon::now()->subMonth())
                    ->orWhereNull('last_checked');
            })
            ->where('is_withdrawn', 0)
            ->has('sellers', '>=', $numSellers)
            ->where('lowest_price', '<=', $lowestPrice)
            ->where('avg_low_price', '<=', $avgLowPrice)
            ->where('semrush_AS', '>=', $minSRAS)
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
            ++$this->numApiCalls;
            $response = $this->client->request('GET', "https://domain-metrics-check.p.rapidapi.com/domain-metrics/{$domain}/", [
                'headers' => [
                    'X-RapidAPI-Host' => 'domain-metrics-check.p.rapidapi.com',
                    'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
                ],
            ]);

            $body = $response->getBody();
            $data = json_decode($body, true);

            return $data;
        }
        catch (\GuzzleHttp\Exception\ClientException $e)
        {
            echo $e->getMessage();
            return false;
        }
    }
}
