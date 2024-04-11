<?php

namespace App\Filament\Resources\LinkSiteResource\Pages;

use App\Filament\Resources\LinkSiteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLinkSite extends EditRecord
{
    protected static string $resource = LinkSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
