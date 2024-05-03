<?php

namespace App\Filament\Admin\Resources\LinkSiteResource\Pages;

use App\Filament\Admin\Resources\LinkSiteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLinkSites extends ListRecords
{
    protected static string $resource = LinkSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
