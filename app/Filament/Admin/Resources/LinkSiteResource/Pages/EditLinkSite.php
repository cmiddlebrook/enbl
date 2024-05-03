<?php

namespace App\Filament\Admin\Resources\LinkSiteResource\Pages;

use App\Filament\Admin\Resources\LinkSiteResource;
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

    protected function afterSave(): void
    {
        // I tried to use successRedirectUrl() on the EditAction on the resource form, but for some reason
        // it simply is not called, and neither are ANY of the lifecycle hooks! Dunno if that is a Filament 
        // bug or not, but this workaround works. 02/05/2024
        redirect('/admin/link-sites');
    }
}
