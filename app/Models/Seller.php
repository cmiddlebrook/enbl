<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    use HasFactory;

    public function linksForSale()
    {
        // we use hasMany here which is a 1 to many relationship because what we want to return is the 
        // intermediate class of SellerSite which has additional pricing information.
        return $this->hasMany(SellerSite::class);
    }

    public function linkSites()
    {
        // this function isn't really needed but it defines a many to many relationship and allows us to 
        // bypass the SellerSite class and directly access a list of all LinkSite entries associated
        // with this seller. However, we'd not have the pricing information so it's not very useful
        return $this->belongsToMany(LinkSite::class, 'seller_sites', 'seller_id', 'link_site_id');
    }
}
