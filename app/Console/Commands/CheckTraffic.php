<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\LinkSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class CheckTraffic extends Command
{

    protected $signature = 'check-traffic';
    protected $description = 'Checks the SEMRush Traffic & Rankings of some of the link sites';

    protected $client;
    protected $numApiCalls = 0;
    protected $maxApiCalls = 1500;

    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();
    }

    public function handle()
    {
        $this->info('Starting to check domain traffic...');
        $this->checkSites(6);
        echo "{$this->numApiCalls} API calls made\n";
    }



    private function checkSites($minSRAS)
    {
        $sites = $this->getSitesToCheck($minSRAS);
        $numSites = $sites->count();
        echo "Checking {$numSites} with at least {$minSRAS} SEMRush Authority Score\n";

        foreach ($sites as $linkSite)
        {
            if ($this->numApiCalls >= $this->maxApiCalls) break;

            $domain = $linkSite->domain;

            $this->info("Checking {$domain}");
            $data = $this->makeAPICall($domain);
            // \Symfony\Component\VarDumper\VarDumper::dump($data);
            if (!$data) continue;

            $domain = $data['sr_domain'];
            $traffic = $data['sr_traffic'];
            $keywords = $data['sr_kwords'];
            $dlinks = $data['sr_dlinks'];

            // if the domain value comes back as unknown, the API call failed. 
            // the API is temperamental, just skip over it and it will be retried on the next run
            if ($domain == 'unknown') continue;

            if ($domain == 'notfound')
            {
                if ($dlinks == 'notfound')
                {
                    $linkSite->is_withdrawn = 1;
                    $linkSite->withdrawn_reason = 'checkhealth';
                    $linkSite->save();
                    echo "Domain couldn't be accessed, marked for health check\n";
                    continue;
                }

                // there's a value for the domain links but not the domain itself, something is wrong
                // just update the date and try again another time
                $linkSite->last_checked_traffic = Carbon::today();
                $linkSite->save();
                echo "Problem fetching, skipping this run...\n";
                continue;
            }

            // if other values are reported as unknown, they are too low to record, so set to 0
            $linkSite->semrush_traffic = $traffic == 'unknown' ? 0 : $traffic;
            $linkSite->semrush_organic_kw = $keywords == 'unknown' ? 0 : $keywords;
            $linkSite->semrush_perc_english_traffic = 0;
            $linkSite->last_checked_traffic = Carbon::today();

            echo "{$domain} traffic & KW updated!\n";
            $linkSite->save();

        }
    }

    private function getSitesToCheck($minSRAS)
    {
        $sites = LinkSite::withAvgLowPrices()->withLowestPrice()
            ->where(function ($query)
            {
                $query->where('last_checked_traffic', '<', Carbon::now()->subMonth())
                    ->orWhereNull('last_checked_traffic');
            })
            ->where(function ($query)
            {
                $query->where('is_withdrawn', 0)
                    ->orWhereNotIn('withdrawn_reason', ['language', 'subdomain', 'deadsite', 'checkhealth']);
            })
            ->where('semrush_AS', '>=', $minSRAS)
            ->orderBy('last_checked_traffic', 'asc')
            ->orderBy('majestic_trust_flow', 'desc')
            ->orderBy('semrush_AS', 'desc')
            ->get();

        return $sites;
    }

    private function makeAPICall($domain)
    {
        try
        {
            ++$this->numApiCalls;
            sleep(1);
            $response = $this->client->request('GET', "https://seo-rank.my-addr.com/api2/sr/394D46FA5B5CECAC8C20F821CC297F63/{$domain}");

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
