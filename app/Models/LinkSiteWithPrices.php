<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinkSiteWithPrices extends Model
{
    use HasFactory;

    protected $table = 'link_site_with_prices'; // view
    protected $primaryKey = 'link_site_id';
    public $timestamps = false; // no timestamps on views
    
}
