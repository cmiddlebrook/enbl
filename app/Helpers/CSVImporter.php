<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use App\Models\LinkSite;
use Exception;
use Pest\Support\Backtrace;

class CSVImporter
{
    public function import($file)
    {
        if ($file instanceof UploadedFile)
        {
            $handle = fopen($file->getRealPath(), 'r');
            $header = fgetcsv($handle);

            $rowsImported = 0;
            $errorRows = array();
            $numErrors = 0;
            while (($row = fgetcsv($handle)) !== false)
            {
                $rowData = array_combine($header, $row);
                $cleanedData = $this->cleanupDomains($rowData);
                $validator = $this->makeValidator($cleanedData);

                if ($validator->passes())
                {
                    LinkSite::create($cleanedData);
                    $rowsImported++;
                }
                else
                {
                    // store the original data row before any cleanup was done
                    $errorRows[] = ['row' => $rowData, 'errors' => $validator->errors()->all()];
                    $numErrors++;                    
                }
            }

            if ($numErrors > 0)
            {
                $this->notifyFail($errorRows);
            }
            else
            {
                $this->notifySuccess($rowsImported);
            }
        }
        else
        {
            throw new Exception('Invalid file');
        }

        $this->cleanupFiles($handle, $file);
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

    private function notifySuccess($rowsImported)
    {
        Notification::make()
            ->title('Successful import')
            ->body("$rowsImported rows imported")
            ->icon('fas-file-csv')
            ->color('success')
            ->success()
            ->send();
    }

    private function notifyFail($errorRows)
    { 
        $errorString = "";
        foreach($errorRows as $errorRow)
        {
            $errorString .= $this->prettifyDataRow($errorRow['row']);
            $errorString .= "Row contained the following errors: " . implode('/', $errorRow['errors']);
        }

        Notification::make()
            ->title('Validation failed upon import')
            ->body("There was an error in the following data: {$errorString}")
            ->icon('fas-file-csv')
            ->color('danger')
            ->danger()
            ->persistent()
            ->actions([
                Action::make('ok')
                    ->button(),
                Action::make('undo')
                    ->color('gray'),
            ])
            ->send();
    }

    private function prettifyDataRow($dataRow)
    {
        $dataList = '<ul>';
        foreach ($dataRow as $key => $value)
        {
            $dataList .= "<li><strong>{$key}:</strong> {$value}</li>";
        }
        $dataList .= '</ul>';

        return $dataList;
    }

    private function cleanupFiles($handle, $file)
    {
        fclose($handle);
        $file->delete();
    }
}
