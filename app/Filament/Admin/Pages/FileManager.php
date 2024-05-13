<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use App\Helpers\CSVImporter;

class FileManager extends Page implements HasForms
{
    use InteractsWithForms;
    protected static ?string $navigationIcon = 'fas-file-csv';
    protected static string $view = 'filament.pages.file-manager';

    public ?array $linksite_file = []; 
    public ?array $seller_file = []; 

    protected function getFormSchema(): array
    {
        $schema = [
            Grid::make(2)->schema([
                Group::make()->schema([
                    Section::make()->schema([
                        FileUpload::make('csv_file')
                            ->label('Link Sites CSV Data')
                            ->hintIcon('fas-link')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                            ->reactive()
                            ->afterStateUpdated(function ($state)
                            {
                                $csvImporter = new CSVImporter();
                                $csvImporter->importLinkSites($state);
                                $this->notifyResults($csvImporter);
                            }),                            
                    ])
                ])->columnSpan(1)
            ])->statePath('linksite_file'),

            Grid::make(2)->schema([
                Group::make()->schema([
                    Section::make()->schema([
                        FileUpload::make('csv_file')
                            ->label('Seller Sites CSV Data')
                            ->hintIcon('fas-user-secret')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                            ->reactive()
                            ->afterStateUpdated(function ($state)
                            {
                                $csvImporter = new CSVImporter();
                                $csvImporter->importSellerSites($state);
                                $this->notifyResults($csvImporter);
                            })
                    ])
                ])->columnSpan(1)
            ])->statePath('seller_file')
        ];

        return $schema;
    }

    private function notifyResults(CSVImporter $csvImporter)
    {
        $numErrors = $csvImporter->getNumErrors();
        $bodyText = "{$csvImporter->getNumImported()} rows imported, with {$numErrors} errors";

        $notification = Notification::make()
            ->body($bodyText)
            ->icon('fas-file-csv')
            ->persistent();

        if ($numErrors > 0)
        {
            $downloadUrl = url($csvImporter->getErrorFilename());
            $notification->title('INFO: import success, but with errors:');
            $notification->actions([Action::make('download_error_file')->button()->url($downloadUrl)]);
            $notification->color('info');
            $notification->info();
        }
        else
        {
            $notification->title('Import Success!');
            $notification->color('success');
            $notification->success();
        }
        $notification->send();
    }

}
