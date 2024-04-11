<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinkSite extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'ip_address',
        'niches',
        'semrush_AS',
        'semrush_traffic',
        'semrush_perc_english_traffic',
        'semrush_organic_kw',
        'moz_da',
        'moz_pa',
        'moz_perc_quality_bl',
        'moz_spam_score',
        'domain_age',
        'majestic_trust_flow',
        'majestic_citation_flow',
        'ahrefs_domain_rank'
    ];

    public function sellers()
    {
        return $this->hasMany(SellerSite::class);
    }

    public function niches()
    {
        //TODO: grab the niches field, explode it to get a list of ids and then look them up in the Niches table
        return;
    }
}
