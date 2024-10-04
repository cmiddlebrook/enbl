<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

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
            $domain = $linkSite->domain;
            \Symfony\Component\VarDumper\VarDumper::dump($domain);

            $this->info("Checking age of {$domain}");
            $data = $this->makeAPICall($domain);
            if (!$data) break;

            if (!$this->updateCreationDate($linkSite, $data['data']['created_date']))
            {
                if (strpos($domain, '.com.au'))
                {
                    if (!$this->updateCreationDate($linkSite, $data['data']['updated_date']))
                    {
                        echo "{$domain} no valid date found: \n";
                        \Symfony\Component\VarDumper\VarDumper::dump($data);
                        break;
                    }
                }
            }
        }
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
                echo "{$linkSite->domain} updated! Domain age: {$parsedDate} \n";
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
        else
        {
            // couldn't read the date so we'll need to do a manual check
            // set a manual date in the future so I can easily find them (kludge!)
            // $linkSite->domain_creation_date = "2099-09-09";
            echo "{$linkSite->domain} date not found! \n";
            // $linkSite->save();
            // return true;
        }
    }

    private function getSitesToCheck($num = 400)
    {
        $sites = LinkSite::withAvgLowPrices()
            ->where('is_withdrawn', 0)
            ->whereNull('domain_creation_date')
            ->where('domain', 'not like', '%.au') // skip Australian domains, these are not publically available
            ->orderBy('semrush_organic_kw', 'desc')
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
            sleep(2);
            // $response = $this->client->request('GET', "https://domain-age-checker2.p.rapidapi.com/domain-age?url={$domain}", [
            //     'headers' => [
            //         'X-RapidAPI-Host' => 'domain-age-checker2.p.rapidapi.com',
            //         'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
            //     ],
            // ]);

            $response = $this->client->request('GET', "https://whois-lookup10.p.rapidapi.com/domain?domain={$domain}", [
                'headers' => [
                    'X-RapidAPI-Host' => 'whois-lookup10.p.rapidapi.com',
                    'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
                ],
            ]);

            $body = $response->getBody();
            $data = json_decode($body, true);
            \Symfony\Component\VarDumper\VarDumper::dump($data);
            exit;

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
