<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSite extends Model
{
    use HasFactory;

    protected $casts = [
        'is_approved' => 'boolean'
    ];

    protected $fillable = [
        'website_name',
        'domain'
    ];
}
