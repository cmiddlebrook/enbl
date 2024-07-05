<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'notes'
    ];

    public function linkSites()
    {
        return $this->belongsToMany(LinkSite::class, 'seller_sites')            
            ->withPivot('price_guest_post', 'price_link_insertion')
            ->orderByPivot('price_guest_post');
    }

    public function averagePrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->linkSites()->avg('price_guest_post')
        ); 
    }

    public function scopeWithAveragePrice(Builder $query)
    {
        $query->addSelect([
            'average_price' => SellerSite::selectRaw('avg(price_guest_post)')
                ->whereColumn('seller_sites.seller_id', 'sellers.id')
        ]);
    }
}
