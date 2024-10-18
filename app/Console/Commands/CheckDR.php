<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Enums\WithdrawalReasonEnum;
use App\Models\LinkSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckDR extends Command
{
    protected $signature = 'check-dr';
    protected $description = 'Finds the Ahrefs Domain Rank of some link sites';

    protected $client;
    protected $numApiCalls = 0;
    protected $maxApiCalls = 300;
    protected $successfulCalls = 0;

    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();
    }

    public function handle()
    {
        $sites = $this->getSitesToCheck();
        foreach ($sites as $linkSite)
        {
            if ($this->numApiCalls >= $this->maxApiCalls) break;
            $this->checkSite($linkSite);
        }

        echo "{$this->numApiCalls} API calls made\n";
        echo "{$this->successfulCalls} Successful updates made\n";
    }

    private function checkSite($linkSite)
    {
        $this->info("Checking DR of {$linkSite->domain}");
        $this->checkDomainRank($linkSite);
        return;

        // skipping this mechanism for now as will be too expensive to try too many times

        // the API is temperamental, try several times
        for ($numTries = 0; $numTries < 5; $numTries++)
        {
            if ($this->checkDomainRank($linkSite)) return;
            echo ".";
            sleep (1);
        }

        $this->markForManualCheck($linkSite);
    }

    private function checkDomainRank($linkSite)
    {
        $data = $this->makeAPICall($linkSite->domain);

        if (array_key_exists('error', $data)) return false;

        $dr = $data['domain_rating'];
        $this->updateDomainRank($linkSite, $dr);
        return true;
    }

    private function updateDomainRank($linkSite, $dr)
    {
        echo "{$linkSite->domain} has domain rank of {$dr}\n";
        $linkSite->ahrefs_domain_rank = $dr;
        $linkSite->last_checked_dr = Carbon::today();
        $linkSite->save();
        $this->successfulCalls++;
    }

    private function markForManualCheck($linkSite)
    {
        echo " API Call failed, marking site for manual check\n";
        $linkSite->is_withdrawn = 1;
        $linkSite->withdrawn_reason = WithdrawalReasonEnum::CHECKDR;
        $linkSite->last_checked_traffic = Carbon::today();
        $linkSite->save();
    }

    private function getSitesToCheck()
    {
        $sites = LinkSite::where('is_withdrawn', 0)
            ->whereNull('ahrefs_domain_rank') // change this to check on date once gaps filled in
            ->orderBy('semrush_organic_kw', 'asc')
            ->orderBy('majestic_trust_flow', 'desc')
            ->orderBy('semrush_AS', 'desc')
            ->limit(10)
            ->get();

        return $sites;
    }


    private function makeAPICall($domain)
    {
        try
        {
            ++$this->numApiCalls;
            $response = $this->client->request('GET', "https://ahrefs-api.p.rapidapi.com/check-dr-ar?domain={$domain}", [
                'headers' => [
                    'X-RapidAPI-Host' => 'ahrefs-api.p.rapidapi.com',
                    'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
                ],
            ]);


            $body = $response->getBody();
            $data = json_decode($body, true);
            // \Symfony\Component\VarDumper\VarDumper::dump($data);
            // exit;

            return $data;
        }
        catch (\GuzzleHttp\Exception\ClientException $e)
        {
            $errorMessage = $e->getMessage();

            if (strpos($errorMessage, "429 Too Many Requests"))
            {
                echo "Daily API quota reached\n";
                exit;
            }
            echo $errorMessage;
            return false;
        }
    }
}
