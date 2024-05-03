<?php

namespace App\Filament\Admin\Resources\SellerResource\Pages;

use App\Filament\Admin\Resources\SellerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSellers extends ListRecords
{
    protected static string $resource = SellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
