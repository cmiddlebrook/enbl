<?php

namespace App\Helpers;

use App\Models\LinkSite;
use App\Models\Seller;
use App\Models\SellerSite;
use Illuminate\Support\Facades\DB;

class InventoryManager
{
    public static function updateSellerCounts()
    {
        $sellerCounts = DB::table('seller_sites')
            ->join('link_sites', 'link_sites.id', '=', 'seller_sites.link_site_id')
            ->select('link_site_id', DB::raw('count(seller_id) as number_sellers'))
            ->groupBy('link_site_id')
            ->get();

        foreach ($sellerCounts as $count)
        {
            LinkSite::where('id', $count->link_site_id)->update(['number_sellers' => $count->number_sellers]);
        }
    }
}
