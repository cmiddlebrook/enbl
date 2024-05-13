<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Niche extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    public function linkSites()
    {
        return $this->belongsToMany(LinkSite::class, 'link_site_niches');
    }
}
