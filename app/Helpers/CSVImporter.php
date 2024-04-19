<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use App\Models\LinkSite;
use Exception;

class CSVImporter
{
    public function import($file)
    {
        $numImported = 0;
        $numErrors = 0;

        if ($file instanceof UploadedFile)
        {
            $inputFile = fopen($file->getRealPath(), 'r');

            // setup a file to hold any errors
            $errorFilename = 'csv_error_rows_' . time() . '_' . Str::random(4) . '.csv';
            $errorOutputPath = public_path($errorFilename);
            $errorOutputFile = fopen($errorOutputPath, 'w');
            $header = fgetcsv($inputFile);
            $errorHeader = array_merge($header, ['errors']);
            fputcsv($errorOutputFile, $errorHeader);
            
            while (($row = fgetcsv($inputFile)) !== false)
            {
                $rowData = array_combine($header, $row);
                $cleanedData = $this->cleanupDomains($rowData);
                $validator = $this->makeValidator($cleanedData);

                if ($validator->passes())
                {
                    LinkSite::create($cleanedData);
                    $numImported++;
                }
                else
                {
                    $errorMessages = implode('; ', $validator->errors()->all());
                    $errorRow = array_merge($row, [$errorMessages]);
                    fputcsv($errorOutputFile, $errorRow);
                    $numErrors++;
                }
            }
        }
        else
        {
            throw new Exception('Invalid file');
        }

        $downloadUrl = url($errorFilename);
        $this->notifyResults($numImported, $numErrors, $downloadUrl);

        fclose($inputFile);
        fclose($errorOutputFile);
        $file->delete();
    }

    private function notifyResults($numImported, $numErrors, $downloadUrl)
    {
        $bodyText = "{$numImported} rows imported, with {$numErrors} errors"; 

        $notification = Notification::make()
            ->body($bodyText)
            ->icon('fas-file-csv')
            ->persistent();

            if ($numErrors > 0)
            {
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

    private function cleanupDomains(array $rowData)
    {
        foreach ($rowData as $key => $value)
        {
            if ($value === '')
            {
                // convert any empty strings to null values
                $rowData[$key] = null;

                // strip out unwanted domain prefixes
                $rowData['domain'] = preg_replace('/^(http:\/\/|https:\/\/)?(www\.)?/', '', $rowData['domain']);
            }
        }

        return $rowData;
    }

    private function makeValidator(array $data)
    {
        $validator = Validator::make($data, [
            'domain' => [
                'required',
                'regex:/^[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}$/',
                'unique:link_sites,domain'
            ],
            'ip_address' => 'nullable|ipv4',
            'semrush_AS' => 'nullable|numeric|between:0,100',
            'semrush_perc_english_traffic' => 'nullable|numeric|between:0,100',
            'moz_da' => 'nullable|numeric|between:0,100',
            'moz_pa' => 'nullable|numeric|between:0,100',
            'moz_perc_quality_bl' => 'nullable|numeric|between:0,100',
            'moz_spam_score' => 'nullable|numeric|between:0,100',
            'domain_age' => 'nullable|numeric|between:0,100',
            'majestic_trust_flow' => 'nullable|numeric|between:0,100',
            'majestic_citation_flow' => 'nullable|numeric|between:0,100',
            'ahrefs_domain_rank' => 'nullable|numeric|between:0,100',
        ]);

        return $validator;
    }
}
