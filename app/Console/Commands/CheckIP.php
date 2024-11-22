<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Enums\WithdrawalReasonEnum;
use App\Models\LinkSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckIP extends Command
{
    protected $signature = 'check-ip';
    protected $description = 'Finds the IP address of some link sites';

    protected $client;
    protected $numApiCalls = 0;
    protected $invalidDomains = [];
    protected $domainChecks = [];

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
            $domain = $linkSite->domain;

            if (!in_array($domain, $this->invalidDomains))
            {
                $this->info("Checking IP address of {$domain}");
                $this->updateCheckCount($domain);
                $data = $this->makeAPICall($domain);

                $ip = $this->extractIP($data);
                $this->updateIPAddress($linkSite, $ip);
            }

            if ($this->domainChecks[$domain] > 4)
            {
                $this->invalidDomains[] = $domain;
            }
        }

        echo "{$this->numApiCalls} API calls made\n";
    }

    private function extractIP($data)
    {
        $error = "No data returned";

        if ($data)
        {
            if (array_key_exists('a', $data))
            {
                $aRecord = $data['a'];
                if (is_array($aRecord) && array_key_exists(0, $aRecord))
                {
                    $firstRecord = $aRecord[0];
                    if (is_array($firstRecord) && array_key_exists('ip', $firstRecord))
                    {
                        return $firstRecord['ip'];
                    }
                    $error = "Missing ip field";
                }
                $error = "No 0 element in array";
            }
            $error = "Missing a record";
        }

        return $error;
    }

    private function updateCheckCount($domain)
    {
        if (array_key_exists($domain, $this->domainChecks))
        {
            $this->domainChecks[$domain]++;
        }
        else
        {
            $this->domainChecks[$domain] = 1;
        }
    }

    private function updateIPAddress($linkSite, $ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        {
            $linkSite->ip_address = $ip;
            echo "{$linkSite->domain} updated! IP Address: {$ip} \n";
            $linkSite->save();
        }
        else
        {
            $this->markForHealthCheck($linkSite, $ip);
        }
    }

    private function markForHealthCheck($linkSite, $ip)
    {
        echo "Invalid IP: {$ip}, marking site for checking\n";
        $linkSite->is_withdrawn = 1;
        $linkSite->withdrawn_reason = WithdrawalReasonEnum::CHECKHEALTH;
        $linkSite->save();
    }

    private function getSitesToCheck()
    {
        $sites = LinkSite::where('is_withdrawn', 0)
            ->whereNull('ip_address')
            ->orderByDesc('semrush_organic_kw')
            ->orderByDesc('majestic_trust_flow')
            ->orderByDesc('semrush_AS')
            ->limit(490)
            ->get();

        return $sites;
    }


    private function makeAPICall($domain)
    {
        try
        {
            ++$this->numApiCalls;
            $response = $this->client->request('GET', "https://vibrant-dns.p.rapidapi.com/dns/get?domain={$domain}&record_type=a", [
                'headers' => [
                    'X-RapidAPI-Host' => 'vibrant-dns.p.rapidapi.com',
                    'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
                ],
            ]);


            $body = $response->getBody();
            $data = json_decode($body, true);
            // \Symfony\Component\VarDumper\VarDumper::dump($data);

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
            $this->invalidDomains[] = $domain;
            return false;
        }
    }
}
