<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\LinkSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Pdp\Rules;
use Pdp\Domain;
use GuzzleHttp\Client;

class CheckSubdomains extends Command
{
    protected $signature = 'check-subdomains';
    protected $description = 'Checks domains to ensure they are the registrable root and not a subdomain';

    protected $publicSuffixList;

    public function __construct()
    {
        parent::__construct();

        $client = new Client();
        $response = $client->get('https://publicsuffix.org/list/public_suffix_list.dat');
        $publicSuffixListContent = $response->getBody()->getContents();
        $this->publicSuffixList = Rules::fromString($publicSuffixListContent);
    }


    public function handle()
    {
        $domain = 'www.PreF.OkiNawA.jP';

        $result = $this->publicSuffixList->resolve($domain);
        // \Symfony\Component\VarDumper\VarDumper::dump($result);
        echo $result->domain()->toString(). "\n";           //display 'www.pref.okinawa.jp';
        echo $result->subDomain()->toString(). "\n";        //display 'www';
        echo $result->secondLevelDomain()->toString(). "\n"; //display 'pref';
        echo $result->registrableDomain()->toString(). "\n"; //display 'pref.okinawa.jp';
        echo $result->suffix()->toString(). "\n";            //display 'okinawa.jp';


        // $sites = $this->getSitesToCheck();
        // foreach ($sites as $linkSite)
        // {
        //     $domain = $linkSite->domain;
        //     $this->info("Checking root of {$domain}...");

        //     // check what the root is and compare

        //     echo "{$domain} updated! Country Code: $linkSite->country_code \n";
        //     $linkSite->save();

        // }


    }

    private function getSitesToCheck($num = 100)
    {
        $sites = LinkSite::where(function ($query)
        {
            $query->whereNull('is_withdrawn')
                ->orWhere('is_withdrawn', '!=', 1);
        })
            ->orderBy('semrush_AS', 'desc')
            ->limit($num)
            ->get();

        return $sites;
    }
}
