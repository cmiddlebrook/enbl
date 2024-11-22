<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Enums\WithdrawalReasonEnum;
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
    protected $maxApiCalls = 5000;

    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();
    }

    public function handle()
    {
        $this->info('Starting to check domain traffic...');
        $this->checkNewSites();
        $this->updateSites();
        echo "{$this->numApiCalls} API calls made\n";
    }


    private function checkNewSites()
    {
        $sites = $this->getNewSites();
        $numSites = $sites->count();
        echo "Checking {$numSites} new sites\n";

        foreach ($sites as $linkSite)
        {
            if ($this->numApiCalls >= $this->maxApiCalls) break;
            $this->checkSite($linkSite, true);
        }
    }

    private function updateSites()
    {
        $sites = $this->getSitesToUpdate();
        $numSites = $sites->count();
        echo "Checking {$numSites} existing sites \n";

        foreach ($sites as $linkSite)
        {
            if ($this->numApiCalls >= $this->maxApiCalls) break;
            $this->updateTraffic($linkSite);
        }
    }

    private function checkSite($linkSite, $newSite = false)
    {
        $this->info("Checking {$linkSite->domain}");

        if (!$this->updateTraffic($linkSite))
        {
            $this->recordAPICallFailure($linkSite);
        }

        // if ($newSite)
        // {
        //     $this->markForManualCheck($linkSite);
        // }
        // else
        // {
        //     echo "Could not update traffic, leaving unchanged\n";
        // }
    }

    private function updateTraffic($linkSite)
    {
        $lsDomain = $linkSite->domain;
        $data = $this->makeAPICall($lsDomain);
        // \Symfony\Component\VarDumper\VarDumper::dump($data);
        if (!$data) return false;

        $srDomain = $data['sr_domain'];
        $traffic = $data['sr_traffic'];
        $keywords = $data['sr_kwords'];

        if ($srDomain == 'unknown') return false;
        if ($srDomain == 'notfound') return false;

        if ($srDomain == $lsDomain)
        {
            // if other values are reported as unknown, they are too low to record, so set to 0
            $linkSite->semrush_traffic = $traffic == 'unknown' ? 0 : $traffic;
            $linkSite->semrush_organic_kw = $keywords == 'unknown' ? 0 : $keywords;
            $linkSite->semrush_perc_english_traffic = 0;
            $linkSite->last_checked_traffic = Carbon::today();

            echo "{$lsDomain} traffic & KW updated!\n";
            $linkSite->save();
            return true;
        }
    }

    private function recordAPICallFailure($linkSite)
    {
        $this->info($linkSite->domain . ": Failed to retrieve traffic value");
        $linkSite->increment('semrush_traffic_api_failures');
    }

    // private function markForManualCheck($linkSite)
    // {
    //     echo " API Call failed, marking site for manual check\n";
    //     $linkSite->is_withdrawn = 1;
    //     $linkSite->withdrawn_reason = WithdrawalReasonEnum::CHECKTRAFFIC;
    //     $linkSite->last_checked_traffic = Carbon::today();
    //     $linkSite->save();
    // }

    private function getNewSites()
    {
        $sites = LinkSite::whereNull('semrush_traffic')
            ->where('is_withdrawn', 0)
            ->where('semrush_AS', '>', 0)
            ->where('semrush_traffic_api_failures', 0)
            ->orderByDesc('majestic_trust_flow')
            ->orderByDesc('semrush_AS')
            ->get();

        return $sites;
    }

    private function getSitesToUpdate()
    {
        $sites = LinkSite::where('is_withdrawn', 0)
            // ->where(function ($query)
            // {
            //     $query->where('is_withdrawn', 0)
            //         ->orWhereNotIn('withdrawn_reason', ['language', 'subdomain', 'deadsite', 'checkhealth', 'checktraffic']);
            // })
            ->where('last_checked_traffic', '<', Carbon::now()->subMonth(1))
            ->orderBy('last_checked_traffic', 'asc')
            ->orderByDesc('semrush_organic_kw')
            ->orderByDesc('majestic_trust_flow')
            ->orderByDesc('semrush_AS')
            ->get();

        return $sites;
    }

    private function makeAPICall($domain)
    {
        try
        {
            ++$this->numApiCalls;
            $response = $this->client->request('GET', "https://seo-rank.my-addr.com/api2/sr/394D46FA5B5CECAC8C20F821CC297F63/{$domain}");

            $body = $response->getBody();
            $data = json_decode($body, true);
            // \Symfony\Component\VarDumper\VarDumper::dump($data);
            // exit;

            return $data;
        }
        catch (\GuzzleHttp\Exception\ClientException $e)
        {
            echo $e->getMessage();
            return false;
        }
    }
}
