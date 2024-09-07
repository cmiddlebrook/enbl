<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinkSiteHealth extends Model
{
    use HasFactory;

    protected $table = 'link_site_health';

    protected $fillable =
    [
        'link_site_id',
        'check_date',
        'up',
    ];

    public function linkSite()
    {
        return $this->belongsTo(LinkSite::class);
    }
}
