<?php

namespace App\Filament\Pages;

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
                                $this->notifyResults($csvImporter);
                            })
                    ])
                ])->columnSpan(1)
            ])->statePath('file')
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
