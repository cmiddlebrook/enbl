<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

use phpDocumentor\Reflection\Types\Boolean;
use App\Enums\WithdrawalReasonEnum;


class LinkSite extends Model
{
    use HasFactory;

    protected $casts = [
        'is_withdrawn' => 'boolean',
        'withdrawn_reason' => WithdrawalReasonEnum::class
    ];

    protected $fillable = [
        'domain',
        'ip_address',
        'last_checked',
        'is_withdrawn',
        'withdrawn_reason',
        'niches',
        'semrush_AS',
        'semrush_traffic',
        'semrush_perc_english_traffic',
        'semrush_organic_kw',
        'moz_da',
        'moz_pa',
        'moz_rank',
        'moz_links',
        'domain_age',
        'majestic_trust_flow',
        'majestic_citation_flow',
        'majestic_ref_domains',
        'majestic_ref_edu',
        'majestic_ref_gov',
        'facebook_shares',
        'ahrefs_domain_rank'
    ];

    public function sellers()
    {
        return $this->belongsToMany(Seller::class, 'seller_sites')
            ->withPivot('price_guest_post', 'price_link_insertion')
            ->orderByPivot('price_guest_post');
    }

    public function lowestPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->sellers()->min('price_guest_post')
        );
    }

    public function avgLowPrices(): Attribute
    {
        return Attribute::make(
            get: function () 
            {
                $prices = $this->sellers()
                    ->orderBy('price_guest_post', 'asc')
                    ->limit(3)
                    ->pluck('price_guest_post');

                if ($prices->count() < 3) 
                {
                    return $this->lowestPrice();
                }

                return $prices->avg();
            }
        );
    }

    public function niches()
    {
        return $this->belongsToMany(Niche::class, 'link_site_niches');
    }

}
