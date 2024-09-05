<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\LinkSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckIP extends Command
{
    protected $signature = 'check-ip';
    protected $description = 'Finds the IP address of some link sites';

    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new \GuzzleHttp\Client();
    }

    public function handle()
    {
        for ($i = 0; $i < 20; $i++)
        {
            $sites = $this->getSitesToCheck();
            echo $sites->count() . " sites to be checked\n";
            foreach ($sites as $linkSite)
            {
                usleep(200000);
                $domain = $linkSite->domain;
                $this->info("Checking IP address of {$domain}");
                $data = $this->makeAPICall($domain);
                if ($data)
                {                    
                    $ip = $data['ip'];
                    $this->updateIPAddress($linkSite, $ip);
                }
            }
        }
    }

    private function updateIPAddress($linkSite, $ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        {
            $linkSite->ip_address = $ip;
            echo "{$linkSite->domain} updated! IP Address: {$ip} \n";
            $linkSite->save();
            return true;
        }
        return false;
    }

    private function getSitesToCheck($num = 50)
    {
        $sites = LinkSite::withAvgLowPrices()->withLowestPrice()
            ->where('is_withdrawn', 0)
            ->whereNull('ip_address')
            ->has('sellers', '>=', 2)
            ->where('avg_low_price', '<=', 25)
            ->where('semrush_AS', '>=', 10)
            ->orderBy('avg_low_price', 'asc')
            ->orderBy('majestic_trust_flow', 'desc')
            ->orderBy('semrush_AS', 'desc')
            ->limit($num)
            ->get();

        return $sites;
    }


    private function makeAPICall($domain)
    {
        try
        {
            $response = $this->client->request('GET', "https://seo-api2.p.rapidapi.com/domain-to-ip?url=https://{$domain}", [
                'headers' => [
                    'X-RapidAPI-Host' => 'seo-api2.p.rapidapi.com',
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
            if (strpos($errorMessage, "400 Bad Request"))
            {
                echo "Invalid IP address:\n";
                exit;
            }
            echo $errorMessage;

            return false;
        }
    }
}
