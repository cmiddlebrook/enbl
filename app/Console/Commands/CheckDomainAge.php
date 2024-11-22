<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Enums\WithdrawalReasonEnum;
use App\Models\LinkSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class CheckDomainAge extends Command
{
    protected $signature = 'check-domain-age';
    protected $description = 'Checks the age of the domain of some link sites';

    protected $client;
    protected $numApiCalls = 0;
    protected $maxApiCalls = 300;


    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();
    }

    public function handle()
    {
        $sites = $this->getSitesToCheck();
        echo $sites->count() . " sites to be checked\n";
        foreach ($sites as $linkSite)
        {
            try
            {
                $this->info("Checking age of {$linkSite->domain}");

                if (!$this->checkDomainAge($linkSite))
                {
                    $this->markForHealthCheck($linkSite);
                }
            }
            catch (Exception $e)
            {
                $this->markForHealthCheck($linkSite);
                echo "Exception encountered:\n" . $e->getMessage();
            }
        }
    }

    private function checkDomainAge($linkSite)
    {
        $domain = $linkSite->domain;
        $result = false;
        $data = $this->makeAPICall($domain);
        // \Symfony\Component\VarDumper\VarDumper::dump($data);
        if ($data)
        {
            $whois = $data['whois'];
            if (array_key_exists('Registered on', $whois))
            {
                $createdDate = $whois['Registered on'];
            }
            else if (array_key_exists('Creation Date', $whois))
            {
                $createdEntry = $createdEntry = $whois['Creation Date'];
                if (is_array($createdEntry))
                {
                    $createdEntry = $createdEntry[0];
                }
                $createdDate = substr($createdEntry, 0, 10); // format: "2000-06-14T07:45:27Z"
            }
            else
            {
                $whoisKeys = array_keys($whois);
                // \Symfony\Component\VarDumper\VarDumper::dump($whoisKeys);
                $createdEntry = $whoisKeys[6];
                $createdDate = substr($createdEntry, 18, 10);   // format: "Record created on 2006-03-25 16"    
            }
            // \Symfony\Component\VarDumper\VarDumper::dump($createdDate);
            $result = $this->updateCreationDate($linkSite, $createdDate);
        }
        return $result;
    }

    private function updateCreationDate($linkSite, $date)
    {
        if (!is_null($date))
        {
            try
            {
                $dateString = str_replace('before', '1-', $date);
                $parsedDate = Carbon::parse($dateString)->toDateString();
                if ($parsedDate == Carbon::today()->toDateString())
                {
                    echo "Parsed as today's date:\n";
                    \Symfony\Component\VarDumper\VarDumper::dump($dateString);
                    exit;
                }

                $linkSite->domain_creation_date = $parsedDate;
                echo "{$linkSite->domain} updated! Creation date: {$parsedDate} \n";
                $linkSite->save();
                return true;
            }
            catch (Exception $e)
            {
                $errorMessage = $e->getMessage();
                echo $errorMessage;
                return false;
            }
        }
        return false;
    }

    private function markForHealthCheck($linkSite)
    {
        echo "Marking site for manual check\n";
        $linkSite->is_withdrawn = 1;
        $linkSite->withdrawn_reason = WithdrawalReasonEnum::CHECKAGE;
        $linkSite->save();
    }

    private function getSitesToCheck($num = 300)
    {
        $sites = LinkSite::where('is_withdrawn', 0)
            ->whereNull('domain_creation_date')
            ->where('domain', 'not like', '%.au') // skip Australian domains, these are not publically available
            // ->where('domain', 'thekochfamilyblog.com') // test individual domain
            ->orderByDesc('semrush_organic_kw')
            ->orderByDesc('majestic_trust_flow')
            ->orderByDesc('semrush_AS')
            ->limit($num)
            ->get();

        return $sites;
    }


    private function makeAPICall($domain)
    {
        try
        {
            sleep(1);
            if ($this->numApiCalls >= $this->maxApiCalls) return false;
            ++$this->numApiCalls;
            $response = $this->client->request('GET', "https://whois-lookup10.p.rapidapi.com/domain?domain={$domain}", [
                'headers' => [
                    'X-RapidAPI-Host' => 'whois-lookup10.p.rapidapi.com',
                    'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
                ],
            ]);

            $body = $response->getBody();
            $data = json_decode($body, true);
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
