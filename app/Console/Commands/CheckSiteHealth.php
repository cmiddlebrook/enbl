<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

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
    protected $maxApiCalls = 500;

    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();
    }

    public function handle()
    {
        $this->checkDownSites();
        $this->makeNewChecks();
    }

    private function checkDownSites()
    {
        $downSites = $this->getDownSites();
        $this->info("Checking " . $downSites->count() . " sites that were previously down");
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
                // what?? 
            }
        }
    }

    private function makeNewChecks()
    {
        $sites = $this->getSitesToCheck();
        $this->checkSites($sites);
    }

    private function checkSites($sites)
    {
        $this->info($sites->count() . " sites to be checked");
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

    private function deleteHealthChecks($siteId)
    {
        LinkSiteHealth::where('link_site_id', $siteId)->delete();
    }

    private function updateCheckDate($linkSite)
    {
        // $this->info("Updating check date for {$linkSite->domain}");
        $linkSite->last_checked_health = Carbon::now();
        $linkSite->save();
    }

    private function updateStatus($linkSite, $status)
    {
        if ($status == "Up" || $status == "Down")
        {
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
            $this->info("Error updating health status of {$linkSite->domain}");
        }
    }

    private function getSitesToCheck($num = 900)
    {
        $sites = LinkSite::withAvgLowPrices()->withLowestPrice()
            ->where(function ($query)
            {
                $query->where('last_checked_health', '<', Carbon::now()->subMonth())
                    ->orWhereNull('last_checked_health');
            })
            ->where('is_withdrawn', 0)
            ->has('sellers', '>=', 3)
            ->where('semrush_AS', '>=', 4)
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

    private function getDeadSites()
    {
        // sites that have been checked at least 24 times have been down at least a day - I'll class that as dead
        return LinkSiteHealth::with('linkSite')
            ->select('link_site_id')
            ->groupBy('link_site_id')
            ->havingRaw('COUNT(*) >= 24')
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
                $this->info("Daily API quota reached");
                exit;
            }
            echo $errorMessage;

            return false;
        }
    }
}
