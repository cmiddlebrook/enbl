<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Enums\WithdrawalReasonEnum;
use App\Models\LinkSite;
use App\Models\LinkSiteHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Exception;

class CheckSiteHealth extends Command
{
    protected $signature = 'check-site-health';
    protected $description = 'Checks whether link sites are up or down';

    public function handle()
    {
        // $this->displayManualChecks();
        $this->withdrawDeadSites();
        // $this->checkDeadSites();
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

        echo $results->count() . " need checking manually\n";
        $maxChecks = (int) $this->ask('How many manual checks do you want to do?');

        $checksDone = 0;
        foreach ($results as $result)
        {
            if ($checksDone >= $maxChecks) return;

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

            ++$checksDone;
        }

        // \Symfony\Component\VarDumper\VarDumper::dump($results);
    }

    private function withdrawDeadSites()
    {
        // this query brings back all sites that have had at least 20 separate health checks
        // and have been recorded as down for at least 60 hours
        $sites = LinkSiteHealth::select('link_site_health.link_site_id', 'link_sites.domain')
            ->join('link_sites', 'link_site_health.link_site_id', '=', 'link_sites.id')
            ->selectRaw('COUNT(link_site_health.id) as check_count, MIN(link_site_health.check_date) as earliest_check')
            ->groupBy('link_site_health.link_site_id', 'link_sites.domain')
            ->havingRaw('COUNT(link_site_health.id) >= 20')
            ->havingRaw('MIN(link_site_health.check_date) < NOW() - INTERVAL 60 HOUR')
            ->orderByDesc('check_count')
            ->get();

        foreach ($sites as $linkSite)
        {
            $siteId = $linkSite->link_site_id;
            $domain = $linkSite->domain;
            $this->markSiteDead($siteId);
            echo "{$domain} has been marked as dead\n";
        }
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
            $response = $this->checkSite($domain);

            if ($response->successful())
            {
                $this->info("{$domain} is now UP, deleting health checks :-)");
                $this->deleteHealthChecks($linkSite->id);
            }
            else
            {
                $this->info("{$domain} is still DOWN. recording new check date");
                $this->recordDownStatus($linkSite);
            }
        }
    }

    private function checkDeadSites()
    {
        $deadSites = LinkSite::where('is_withdrawn', 1)
            ->where('withdrawn_reason', WithdrawalReasonEnum::DEADSITE)
            ->get();

        echo "Checking " . $deadSites->count() . " sites that are marked DEAD\n";
        foreach ($deadSites as $linkSite)
        {
            $domain = $linkSite->domain;
            $response = $this->checkSite($domain);

            if ($response->successful())
            {
                echo "{$domain} was marked as dead, now resurrected!\n";
                $linkSite->is_withdrawn = 0;
                $linkSite->withdrawn_reason = null;
                $linkSite->save();
            }
            else
            {
                $this->info("$domain is still dead...");
            }
        }
    }

    private function checkMarkedSites()
    {
        $markedSites = LinkSite::where('is_withdrawn', 1)
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
            $response = $this->checkSite($domain);

            $this->updateStatus($linkSite, $response);
        }
    }

    private function checkSite($domain)
    {
        $this->info("Checking status of {$domain}");
        return $this->makeAPICall($domain);
    }

    private function recordDownStatus($linkSite)
    {
        $siteId = $linkSite->id;
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

    private function updateStatus($linkSite, $response)
    {
        if ($linkSite->withdrawn_reason == WithdrawalReasonEnum::CHECKHEALTH)
        {
            $linkSite->is_withdrawn = false;
            $linkSite->withdrawn_reason = null;
        }

        if (!$response->successful())
        {
            echo "$linkSite->domain had status code: {$response->status()}\n";
            $this->recordDownStatus($linkSite);
        }

        $linkSite->last_checked_health = Carbon::now();
        $linkSite->save();
    }

    private function getSitesToCheck($num = 500)
    {
        $sites = LinkSite::where(function ($query)
        {
            $query->where('last_checked_health', '<', Carbon::now()->subWeek())
                ->orWhereNull('last_checked_health');
        })
            ->where('is_withdrawn', 0)
            ->orderBy('last_checked_health', 'asc')
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
            return Http::timeout(20)->get("https://{$domain}");

            // \Symfony\Component\VarDumper\VarDumper::dump($response);
            // exit;
        }
        catch (\Exception $e)
        {
            // echo "Exception!!!\n";
            // echo $e->getMessage();

            $failedResponse = new \GuzzleHttp\Psr7\Response(
                520, // HTTP status code
                [],  // Headers can be empty for failure
                $e->getMessage() // Body contains the exception message
            );

            // Wrap the Guzzle response into an Illuminate response that behaves like the one from Http::get()
            return new \Illuminate\Http\Client\Response($failedResponse);
        }
    }
}
