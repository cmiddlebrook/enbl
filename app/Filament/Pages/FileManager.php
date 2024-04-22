<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use App\Helpers\CSVImporter;
use App\Livewire\ImportErrors;

class FileManager extends Page implements HasForms
{
    use InteractsWithForms;
    protected static ?string $navigationIcon = 'fas-file-csv';
    protected static string $view = 'filament.pages.file-manager';

    public ?array $file = []; // hold the uploaded csv file

    protected function getFormSchema(): array
    {
        $schema = [
            Grid::make(2)->schema([
                Group::make()->schema([
                    Section::make()->schema([
                        FileUpload::make('csv_file')
                            ->label('Link Sites CSV Data')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                            ->reactive()
                            ->afterStateUpdated(function ($state)
                            {
                                $csvImporter = new CSVImporter();
                                $csvImporter->import($state);
                            })
                    ])
                ])->columnSpan(1)
            ])->statePath('file')
        ];

        return $schema;
    }

}
