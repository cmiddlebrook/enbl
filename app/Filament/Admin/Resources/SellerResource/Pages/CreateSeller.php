<?php

namespace App\Filament\Admin\Resources\SellerResource\Pages;

use App\Filament\Admin\Resources\SellerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSeller extends CreateRecord
{
    protected static string $resource = SellerResource::class;
}
