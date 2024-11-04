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
    protected $numApiErrors = 0;
    protected $maxApiErrors = 3;

    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();
    }

    public function handle()
    {
        // $this->displayManualChecks();
        $this->withdrawDeadSites();
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
            $data = $this->checkSite($domain);

            if (!$data) continue;

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

    private function getSitesToCheck($num = 500)
    {
        // $sites = LinkSite::withLowestPrice()
        //     ->where(function ($query)
        //     {
        //         $query->where('last_checked_health', '<', Carbon::now()->subMonth())
        //             ->orWhereNull('last_checked_health');
        //     })
        //     ->where('is_withdrawn', 0)
        //     ->orderBy('last_checked_health', 'asc')
        //     ->orderBy('majestic_trust_flow', 'desc')
        //     ->orderBy('semrush_AS', 'desc')
        //     ->limit($num)
        //     ->get();


        $sites = LinkSite::select('link_sites.*', 'p.lowest_price', 'p.fourth_lowest_price', 'p.price_difference_percentage')
            ->join('link_site_with_prices as p', 'link_sites.id', '=', 'p.link_site_id')
            ->where(function ($query)
            {
                $query->where('link_sites.last_checked_health', '<', Carbon::now()->subMonth())
                    ->orWhereNull('link_sites.last_checked_health');
            })
            ->where('link_sites.is_withdrawn', 0)
            ->orderBy('link_sites.last_checked_health', 'asc')
            ->orderBy('link_sites.majestic_trust_flow', 'desc')
            ->orderBy('link_sites.semrush_AS', 'desc')
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
        catch (\GuzzleHttp\Exception\RequestException $e)
        {
            if ($this->numApiErrors >= $this->maxApiErrors)
            {
                echo "Too many API errors, exiting script\n";
                exit;
            }
            ++$this->numApiErrors;

            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, "429 Too Many Requests"))
            {
                echo "API quota reached, exiting script\n";
                exit;
            }
            else if (strpos($errorMessage, "The API is unreachable"))
            {
                $this->info("API Unreachable, waiting a few seconds ");
                for ($i = 0; $i < 10; ++$i)
                {
                    echo '.';
                    sleep(1);
                }
                echo "\n";
                return false;
            }

            echo $errorMessage;
            return false;
        }
    }
}
