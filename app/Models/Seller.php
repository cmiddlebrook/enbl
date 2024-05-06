<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'email2',
        'notes'
    ];

    public function linkSites()
    {
        return $this->belongsToMany(LinkSite::class, 'seller_sites')->withPivot('price_guest_post', 'price_link_insertion');
    }
}
