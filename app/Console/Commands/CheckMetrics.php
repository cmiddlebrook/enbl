<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckMetrics extends Command
{

    protected $signature = 'check-metrics';

    protected $description = 'Checks the metrics of some of the link sites';

    public function handle()
    {
        $options = ['Moz & Majestic', 'SEMRush KW & Links', 'Ahrefs'];
        $action = $this->choice('What metrics would you like to check?', $options, $defaultIndex = 0);

        $this->info('You have chosen to: ' . $action);

        switch ($action)
        {
            case 'Moz & Majestic':
                $this->info('Updating Moz & Majestic metrics');
                break;
            case 'SEMRush KW & Links':
                $this->info('Updating SEMRush keywords and links');
                break;
            case 'Ahrefs':
                $this->info('Updating Ahrefs metrics');
                break;
            default:
                $this->error('No valid action selected!');
                break;
        }

        $this->info('Starting to check domain metrics...');

        $domain = 'filmdaily.co';

        $response = Http::withHeaders([
            'X-RapidAPI-Host' => 'domain-metrics-check.p.rapidapi.com',
            'X-RapidAPI-Key' => 'e795fa7e7dmshec72b0683f03249p1e6cc3jsn5eb61b037996',
        ])->get("https://domain-metrics-check.p.rapidapi.com/domain-metrics/{$domain}/");

        if ($response->successful())
        {
            $body = $response->getBody();
            $data = json_decode($body, true);
            \Symfony\Component\VarDumper\VarDumper::dump($data);
        }
        else
        {
            $this->error('Failed to fetch domain metrics.');
        }
    }
}
