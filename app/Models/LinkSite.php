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

    public function scopeWithLowestPrice(Builder $query): Builder
    {
        return $query->leftJoinSub(
            DB::table('seller_sites')
                ->selectRaw('link_site_id, MIN(price_guest_post) as lowest_price')
                ->groupBy('link_site_id'),
            'lowest_prices',
            'link_sites.id',
            'lowest_prices.link_site_id'
        )->addSelect('link_sites.*', 'lowest_prices.lowest_price');
    }


    public function scopeWithAvgLowPrices(Builder $query): Builder
    {
        $subquery = DB::table('seller_sites')
            ->select('link_site_id', 'price_guest_post')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY link_site_id ORDER BY price_guest_post ASC) as row_num');

        $avgSubquery = DB::table(DB::raw("({$subquery->toSql()}) as ranked"))
            ->mergeBindings($subquery)
            ->where('row_num', '<=', 4)
            ->selectRaw('link_site_id, AVG(price_guest_post) as avg_low_price')
            ->groupBy('link_site_id');

        return $query->leftJoinSub($avgSubquery, 'avg_prices', function ($join)
        {
            $join->on('link_sites.id', '=', 'avg_prices.link_site_id');
        })->addSelect('link_sites.*', 'avg_prices.avg_low_price');
    }






    public function niches()
    {
        return $this->belongsToMany(Niche::class, 'link_site_niches');
    }
}
