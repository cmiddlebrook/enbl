<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Enums\WithdrawalReasonEnum;
use App\Models\LinkSite;
use App\Models\LinkSiteHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use Filament\Forms\Components\Card;

class CheckSiteHealth extends Command
{
    protected $signature = 'check-site-health';
    protected $description = 'Checks whether link sites are up or down';

    protected $numApiCalls = 0;
    protected $maxApiCalls = 1000;

    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();
    }

    public function handle()
    {
        $this->displayManualChecks();
        $this->checkDownSites();
        $this->checkMarkedSites();
        $this->makeNewChecks();
    }

    private function displayManualChecks()
    {
        $results = LinkSiteHealth::select('link_site_health.link_site_id', 'link_sites.domain')
            ->join('link_sites', 'link_site_health.link_site_id', '=', 'link_sites.id')
            ->selectRaw('COUNT(link_site_health.id) as check_count, MIN(link_site_health.check_date) as earliest_check')
            ->groupBy('link_site_health.link_site_id', 'link_sites.domain')
            ->havingRaw('COUNT(link_site_health.id) >= 16')
            ->havingRaw('MIN(link_site_health.check_date) < NOW() - INTERVAL 60 HOUR')
            ->orderByDesc('check_count')
            ->get();

        // Output the results to the console
        foreach ($results as $result)
        {
            $siteId = $result->link_site_id;
            $domain = $result->domain;
            $this->info(sprintf("%-7s %-30s %-5s %-20s", $siteId, $domain, $result->check_count, $result->earliest_check));

            if ($this->confirm('Do you want to mark this site as dead? (y/n)', false))
            {
                $this->markSiteDead($siteId);
                echo "{$domain} has been marked as dead\n";
            }
            else 
            {
                $this->deleteHealthChecks($siteId);
            }
        }

        // \Symfony\Component\VarDumper\VarDumper::dump($results);
    }

    private function checkDownSites()
    {
        $downSites = $this->getDownSites();
        echo "Checking " . $downSites->count() . " sites that were previously down\n";
        // \Symfony\Component\VarDumper\VarDumper::dump($downSites);
        // exit;
        foreach ($downSites as $linkSite)
        {
            $this->updateCheckDate($linkSite);

            $domain = $linkSite->domain;
            $data = $this->checkSite($domain);

            $stillDown = ($data['status'] == 'Down');
            if ($stillDown)
            {
                $this->info("{$domain} is still DOWN. recording new check date");
                $this->recordDownStatus($linkSite->id);
            }
            else if ($data['status'] == 'Up')
            {
                $this->info("{$domain} is now UP, deleting health checks :-)");
                $this->deleteHealthChecks($linkSite->id);
            }
            else
            {
                echo "Error updating health status of {$linkSite->domain}\n";
            }
        }
    }

    private function checkMarkedSites()
    {
        $markedSites = LinkSite::
          where('is_withdrawn', 1)
        ->where('withdrawn_reason', WithdrawalReasonEnum::CHECKHEALTH)
        ->get();

        echo "Checking " . $markedSites->count() . " sites that are marked for checking\n";
        $this->checkSites($markedSites);
    }

    private function makeNewChecks()
    {
        $sites = $this->getSitesToCheck();
        $this->checkSites($sites);
    }

    private function checkSites($sites)
    {
        echo $sites->count() . " sites to be checked\n";
        foreach ($sites as $linkSite)
        {
            $domain = $linkSite->domain;
            $this->info("Checking status of {$domain}");
            $data = $this->makeAPICall($domain);

            $this->updateStatus($linkSite, $data['status']);
            if ($this->numApiCalls >= $this->maxApiCalls) break;
        }
    }

    private function checkSite($domain)
    {
        $this->info("Checking status of {$domain}");
        $data = $this->makeAPICall($domain);
        return $data;
    }

    private function recordDownStatus($siteId)
    {
        $healthRecord = new LinkSiteHealth();
        $healthRecord->link_site_id = $siteId;
        $healthRecord->check_date = Carbon::now();
        $healthRecord->up = 0;
        $healthRecord->save();
    }

    private function markSiteDead($siteId)
    {
        $site = LinkSite::find($siteId);
        $site->is_withdrawn = 1;
        $site->withdrawn_reason = WithdrawalReasonEnum::DEADSITE;
        $site->save();

        $this->deleteHealthChecks($siteId);
    }

    private function deleteHealthChecks($siteId)
    {
        LinkSiteHealth::where('link_site_id', $siteId)->delete();
    }

    private function updateCheckDate($linkSite)
    {
        $linkSite->last_checked_health = Carbon::now();
        $linkSite->save();
    }

    private function updateStatus($linkSite, $status)
    {
        if ($status == "Up" || $status == "Down")
        {
            if ($linkSite->withdrawn_reason == WithdrawalReasonEnum::CHECKHEALTH)
            {
                $this->info("{$linkSite->domain} was withdrawn for checking and is UP, unwithdrawing");
                $linkSite->is_withdrawn = false;
                $linkSite->withdrawn_reason = null;
            }

            $linkSite->last_checked_health = Carbon::now();
            $linkSite->save();

            if ($status == "Down")
            {
                $this->info("{$linkSite->domain} is DOWN!");

                $healthRecord = new LinkSiteHealth();
                $healthRecord->link_site_id = $linkSite->id;
                $healthRecord->check_date = Carbon::now();
                $healthRecord->up = 0;
                $healthRecord->save();
            }
        }
        else
        {
            echo "Error updating health status of {$linkSite->domain}\n";
        }
    }

    private function getSitesToCheck($num = 800)
    {
        $sites = LinkSite::withAvgLowPrices()->withLowestPrice()
            ->where(function ($query)
            {
                $query->where('last_checked_health', '<', Carbon::now()->subDays(10))
                    ->orWhereNull('last_checked_health');
            })
            ->where('is_withdrawn', 0)
            // ->where('withdrawn_reason', 'language')
            // ->has('sellers', '>=', 1)
            ->where('semrush_AS', '>=', 5)
            ->orderBy('last_checked_health', 'asc')
            ->orderBy('avg_low_price', 'asc')
            ->orderBy('majestic_trust_flow', 'desc')
            ->orderBy('semrush_AS', 'desc')
            ->limit($num)
            ->get();

        return $sites;
    }

    private function getDownSites()
    {
        return LinkSiteHealth::with('linkSite')
            ->get()
            ->pluck('linkSite')
            ->unique('id')
            ->values();
    }

    private function makeAPICall($domain)
    {
        try
        {
            ++$this->numApiCalls;
            $response = $this->client->request('GET', "https://check-if-website-is-up-or-down.p.rapidapi.com/?domain={$domain}", [
                'headers' => [
                    'X-RapidAPI-Host' => 'check-if-website-is-up-or-down.p.rapidapi.com',
                    'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
                ],
            ]);

            $body = $response->getBody();
            $data = json_decode($body, true);
            // \Symfony\Component\VarDumper\VarDumper::dump($data);

            if (is_null($data)) throw new Exception("Null data when checking {$domain}");
            return $data;
        }
        catch (\GuzzleHttp\Exception\ClientException $e)
        {
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, "429 Too Many Requests"))
            {
                $this->info("API quota reached");
                exit;
            }
            echo $errorMessage;
            exit;

            return false;
        }
    }
}
