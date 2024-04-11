<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerSite extends Model
{
    use HasFactory;

    protected $fillable = [
        'price_guest_post',
        'price_link_insertion'
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function linkSite()
    {
        return $this->belongsTo(LinkSite::class);
    }
}
