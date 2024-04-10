<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinkSite extends Model
{
    use HasFactory;

    public function sellers()
    {
        return $this->hasMany(SellerSite::class);
    }

    public function niches()
    {
        //TODO: grab the niches field, explode it to get a list of ids and then look them up in the Niches table
        return ;
    }

}
