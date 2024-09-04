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

        $numSitesChecked = $this->checkPricingBand(10, 25, 5, 10, 0); // $50 band
        // $numSitesChecked += $this->checkPricingBand(10, 50, 10, 15, $numSitesChecked); // $100 band
        // $numSitesChecked += $this->checkPricingBand(15, 85, 15, 20, $numSitesChecked); // $175 band
        // $numSitesChecked += $this->checkPricingBand(15, 135, 15, 25, $numSitesChecked); // $275 band
        $numSitesChecked += $this->checkPricingBand(15, 200, 20, 30, $numSitesChecked); // $400 band
    }

    private function checkPricingBand($bandLowPrice, $bandMaxPrice, $priceIncrement, $minSEMRushAS, $numSitesChecked)
    {
        echo "Checking Pricing Band from \${$bandLowPrice} to \${$bandMaxPrice}\n";
        echo "Checked {$numSitesChecked} domains so far this run\n";
        for ($startPrice = $bandLowPrice; $startPrice <= $bandMaxPrice; $startPrice += $priceIncrement)
        {
            // echo "startPrice: {$startPrice}\n";

            $lowPrice = $startPrice;            
            for($avgLowPrice = $lowPrice + $priceIncrement; $avgLowPrice <= $startPrice * 2; $avgLowPrice += $priceIncrement)
            {
                // echo "lowPrice: {$lowPrice}\n";
                // echo "avgLowPrice: {$avgLowPrice}\n";

                $numSitesChecked += $this->checkSites(2, $lowPrice, $avgLowPrice, $minSEMRushAS);
            }
        }
        return $numSitesChecked;
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
            sleep(0.05);
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
        }

        return $numSites;
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
