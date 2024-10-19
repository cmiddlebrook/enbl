<?php

namespace App\Filament\Admin\Pages;

use Illuminate\Support\Str;
use Filament\Pages\Page;
use Filament\Forms\Get;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use App\Helpers\CSVImporter;
use App\Helpers\CSVExporter;

class FileManager extends Page implements HasForms
{
    use InteractsWithForms;
    protected static ?string $navigationIcon = 'fas-file-csv';
    protected static string $view = 'filament.pages.file-manager';

    public ?array $linksite_file = [];
    public ?array $seller_file_info = [];

    public function mount(): void
    {
        $this->form->fill();
    }

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
                            ->reactive(),

                        Toggle::make('dummy')->label('Dummy toggle - don\'t click!')->reactive(),

                        \Filament\Forms\Components\Actions::make([
                            \Filament\Forms\Components\Actions\Action::make('upload_linksites_File')
                                ->label('Upload Link Sites File')
                                ->action('uploadLinkSitesFile')
                        ])

                    ])->statePath('linksite_file')
                ])->columnSpan(1),

                Group::make()->schema([
                    Section::make()->schema([

                        FileUpload::make('csv_file')
                            ->label('Seller Sites CSV Data')
                            ->hintIcon('fas-user-secret')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                            ->reactive(),

                        Toggle::make('is_general')->label('Mark as General')->reactive(),

                        \Filament\Forms\Components\Actions::make([
                            \Filament\Forms\Components\Actions\Action::make('upload_seller_file')
                                ->label('Upload Seller File')
                                ->action('uploadSellerFile')

                        ]),

                    ])->statePath('seller_file_info')

                ])->columnSpan(1)
            ]),

            Grid::make(2)->schema([
                Group::make()->schema([
                    Section::make('Google Sheet')->schema([

                        \Filament\Forms\Components\Actions::make([
                            \Filament\Forms\Components\Actions\Action::make('export_google_sheet')
                                ->label('Export Google Sheet')
                                ->action('exportGoogleSheet')

                        ]),

                    ])->statePath('google_sheet_info')
                ])->columnSpan(1)
            ])

        ];

        return $schema;
    }

    public function uploadSellerFile()
    {
        $csvImporter = new CSVImporter();
        $csvImporter->importSellerSites($this->seller_file_info);
        $this->notifyResults($csvImporter);
    }

    public function uploadLinkSitesFile()
    {
        $csvImporter = new CSVImporter();
        $csvImporter->importLinkSites($this->linksite_file);
        $this->notifyResults($csvImporter);
    }

    public function exportGoogleSheet()
    {
        $csvExporter = new CSVExporter();
        $csvExporter->exportGoogleSheetCSV();

        $allSitesUrl = url($csvExporter->getAllSitesFilename());
        $this->sendPopupNotification('All Sites CSV', $allSitesUrl);

        $ukOnlyUrl = url($csvExporter->getUKOnlyFilename());
        $this->sendPopupNotification('UK Only CSV', $ukOnlyUrl);
    }

    private function sendPopupNotification($label, $url)
    {
        $slug = Str::slug($label);
        $notification = Notification::make()
            ->icon('fas-file-csv')
            ->persistent();

        $downloadUrl = url($url);
        $notification->actions([\Filament\Notifications\Actions\Action::make($slug)->label($label)->button()->url($downloadUrl)]);
        $notification->color('success');
        $notification->success();
        $notification->send();
    }

    private function notifyResults(CSVImporter $csvImporter)
    {
        if (!$csvImporter->isFileValid()) return;

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
            $notification->actions([\Filament\Notifications\Actions\Action::make('download_error_file')->button()->url($downloadUrl)]);
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
