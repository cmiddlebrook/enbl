<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\LinkSite;
use App\Models\Seller;
use App\Models\SellerSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurgeDomains extends Command
{
    protected $signature = 'purge-domains';
    protected $description = 'Removed unwanted domains from the database';


    public function handle()
    {
        $this->deleteSpamSites();
        $this->deleteLowAuthoritySites('semrush_AS', 5);
        $this->deleteLowAuthoritySites('moz_da', 10);
        $this->deleteLowAuthoritySites('moz_pa', 10);
        $this->deleteLowAuthoritySites('majestic_trust_flow', 3);
        $this->deleteLowAuthoritySites('semrush_traffic', 100);
        $this->deleteLowAuthoritySites('semrush_organic_kw', 50);
        // $this->deleteHighPrices();
    }

    private function deleteSpamSites()
    {
        $sites = LinkSite::where('is_withdrawn', 1)->where('withdrawn_reason', 'spam')->get();
        $this->deleteSellerSiteEntries($sites);
        $this->deleteLinkSites();
    }

    private function deleteLowAuthoritySites($field, $minValue)
    {
        $sites = LinkSite::whereNotNull($field)->where($field, '<=', $minValue)->get();
        $this->deleteSellerSiteEntries($sites);
        $this->deleteLinkSites();
    }

    private function deleteHighPrices()
    {
        $sites = $this->getSitesWithLotsOfSellers(10);
        
        foreach ($sites as $site)
        {
            $avgLowPrice = $site->avg_low_price;
            $maxPrice = $avgLowPrice < 20 ? $avgLowPrice * 2 : $avgLowPrice * 1.5;
            $sellers = $site->sellers;
            $this->info($site->domain . " has " . $sellers->count() . " sellers with an average price: {$avgLowPrice}, max price: {$maxPrice}");

            $sellersDeleted = 0;
            foreach ($sellers as $seller)
            {
                $price = $seller->pivot->price_guest_post;
                // echo $site->domain . " is sold by " . $seller->email . " for \${$price}\n";

                if ($price > $maxPrice)
                {
                    echo "{$seller->email} sells for {$price} which is too expensive!\n";
                    $this->deleteSellerSite($site->id, $seller->id);
                    ++$sellersDeleted;
                }
            }
            if ($sellersDeleted > 0) echo "{$sellersDeleted} sellers deleted\n";

        }
    }

    private function deleteSellerSiteEntries($sites)
    {
        foreach ($sites as $site)
        {
            $linkSiteId = $site->id;
            $domain = $site->domain;
            $sellers = $site->sellers;

            foreach ($sellers as $seller)
            {                
                $sellerId = $seller->id;
                $email = $seller->email;
                echo "Deleting {$domain} \t from seller {$email} \n";
                $this->deleteSellerSite($linkSiteId, $sellerId);
            }
        }
    }

    private function deleteLinkSites()
    {
        $sites = LinkSite::has('sellers', 0)->get();

        foreach ($sites as $site)
        {
            $domain = $site->domain;
            $sellers = $site->sellers;
            if ($sellers->count() != 0)
            {
                echo $domain . " still has sellers!...\n";
                \Symfony\Component\VarDumper\VarDumper::dump($sellers);
                exit;
            }

            echo "Deleting {$domain} \n";
            $site->delete();
        }
    }

    private function deleteSellerSite($linkSiteId, $sellerId)
    {
        SellerSite::where('link_site_id', $linkSiteId)->where('seller_id', $sellerId)->delete();
        // \Symfony\Component\VarDumper\VarDumper::dump($sellerSite);
    }

    private function getSitesWithLotsOfSellers($minSellers)
    {
        $sites = LinkSite::withAvgLowPrices()
            ->has('sellers', '>=', $minSellers)
            ->orderBy('avg_low_price', 'asc')
            ->orderBy('majestic_trust_flow', 'desc')
            ->orderBy('semrush_AS', 'desc')
            // ->limit(1000)
            ->get();

        return $sites;
    }


}
