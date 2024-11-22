<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

use phpDocumentor\Reflection\Types\Boolean;
use App\Enums\WithdrawalReasonEnum;


class LinkSite extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $casts = [
        'is_withdrawn' => 'boolean',
        'withdrawn_reason' => WithdrawalReasonEnum::class,
    ];

    protected $fillable = [
        'domain',
        'is_withdrawn',
        'withdrawn_reason',
        'niches',
        'semrush_AS',
        'semrush_traffic',
        'semrush_perc_english_traffic',
        'semrush_organic_kw',
        'semrush_traffic_api_failures',
        'last_checked_semrush',
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
        'majestic_TTF0_name',
        'majestic_TTF0_value',
        'majestic_TTF1_name',
        'majestic_TTF1_value',
        'majestic_TTF2_name',
        'majestic_TTF2_value',
        'facebook_shares',
        'last_checked_mozmaj',
        'ahrefs_domain_rank',
        'ip_address',
        'country_code',
        'domain_creation_date',
        'last_checked',
        'last_checked_health',
    ];

    public function sellers()
    {
        return $this->belongsToMany(Seller::class, 'seller_sites')
            ->withPivot('price_guest_post', 'price_link_insertion')
            ->orderByPivot('price_guest_post');
    }

    public function healthChecks()
    {
        return $this->hasMany(LinkSiteHealth::class);
    }

    public function niches()
    {
        return $this->belongsToMany(Niche::class, 'link_site_niches');
    }
}
