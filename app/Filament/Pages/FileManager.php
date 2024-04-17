<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;


class FileManager extends Page implements HasForms
{
    use InteractsWithForms;
    protected static ?string $navigationIcon = 'fas-file-csv';
    protected static string $view = 'filament.pages.file-manager';

    public ?array $file = [];

    protected function getFormSchema(): array
    {
        return [

            Grid::make(2)->schema([
                Group::make()->schema([
                    Section::make('Link Sites')->schema([
                        FileUpload::make('csv_file')
                            ->label('Link Sites CSV Data')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                            ->preserveFilenames()
                            ->reactive()
                            ->afterStateUpdated(function ($state)
                            {
                                $this->processFile($state);
                            })

                    ])
                ])->columnSpan(1)
            ])->statePath('file')
        ];
    }

    public function processFile($file)
    {
        // Check if the file is a valid UploadedFile instance
        if ($file instanceof \Illuminate\Http\UploadedFile)
        {
            // Open the file for reading
            $handle = fopen($file->getRealPath(), 'r');

            // Initialize an array to hold the CSV data
            $data = [];

            // Optionally, you can read the first row as headers
            $headers = fgetcsv($handle);

            // Loop through each line of the file
            while (($row = fgetcsv($handle)) !== false)
            {
                // Here you can combine headers with data if needed
                // $data[] = array_combine($headers, $row);

                // Or just store the CSV row data
                $data[] = $row;
            }

            // Close the file handle
            fclose($handle);
            dd($data);

            // Now $data contains the CSV file data as an array
            // You can process this array as needed
        }
        else
        {
            // Handle the error appropriately
            // For example, you might want to set an error message or log the issue
        }
    }
}
