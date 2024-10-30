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
    protected $numApi1Calls = 0;
    protected $numApi2Calls = 0;
    protected $maxApi1Calls = 400;
    protected $maxApi2Calls = 400;

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

                if (!$this->tryMethod1API($linkSite))
                {
                    echo ("API 1 method failed, trying API 2 method\n");
                    if (!$this->tryMethod2API($linkSite))
                    {
                        $this->markForHealthCheck($linkSite);
                    }
                }
            }
            catch (Exception $e)
            {
                $this->markForHealthCheck($linkSite);
                echo "Exception encountered:\n" . $e->getMessage();
            }
        }
    }

    private function tryMethod1API($linkSite)
    {
        $domain = $linkSite->domain;
        $data = $this->makeAPICallMethod1($domain);
        $result = false;
        // \Symfony\Component\VarDumper\VarDumper::dump($data);
        if ($data && array_key_exists('data', $data))
        {
            $innerData = $data['data'];
            if (is_array($data['data']) && array_key_exists('created_date', $innerData))
            {
                $result = $this->updateCreationDate($linkSite, $data['data']['created_date']);
            }
        }
        return $result;
    }

    private function tryMethod2API($linkSite)
    {
        $domain = $linkSite->domain;
        $result = false;
        $data = $this->makeAPICallMethod2($domain);
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
        echo "Both API Calls failed, marking site for manual check\n";
        $linkSite->is_withdrawn = 1;
        $linkSite->withdrawn_reason = WithdrawalReasonEnum::CHECKAGE;
        $linkSite->save();
    }

    private function getSitesToCheck($num = 400)
    {
        $sites = LinkSite::where('is_withdrawn', 0)
            ->whereNull('domain_creation_date')
            ->where('domain', 'not like', '%.au') // skip Australian domains, these are not publically available
            // ->where('domain', 'thekochfamilyblog.com') // test individual domain
            ->orderBy('semrush_organic_kw', 'desc')
            ->orderBy('majestic_trust_flow', 'desc')
            ->orderBy('semrush_AS', 'desc')
            ->limit($num)
            ->get();

        return $sites;
    }

    private function makeAPICallMethod1($domain)
    {
        try
        {
            sleep(2);
            if ($this->numApi1Calls >= $this->maxApi1Calls) return false;
            ++$this->numApi1Calls;
            $response = $this->client->request('GET', "https://domain-age-checker2.p.rapidapi.com/domain-age?url={$domain}", [
                'headers' => [
                    'X-RapidAPI-Host' => 'domain-age-checker2.p.rapidapi.com',
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


    private function makeAPICallMethod2($domain)
    {
        try
        {
            sleep(2);
            if ($this->numApi2Calls >= $this->maxApi2Calls) return false;
            ++$this->numApi2Calls;
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
