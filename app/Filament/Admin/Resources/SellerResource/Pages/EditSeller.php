<?php

namespace App\Filament\Admin\Resources\SellerResource\Pages;

use App\Filament\Admin\Resources\SellerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeller extends EditRecord
{
    protected static string $resource = SellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        redirect('/admin/sellers');
    }
}
