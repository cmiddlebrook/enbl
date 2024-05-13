<?php

namespace App\Filament\Admin\Resources\NicheResource\Pages;

use App\Filament\Admin\Resources\NicheResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNiche extends EditRecord
{
    protected static string $resource = NicheResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
