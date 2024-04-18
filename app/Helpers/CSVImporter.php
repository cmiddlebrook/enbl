<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use App\Models\LinkSite;

class CSVImporter 
{
    public function import($file)
    {
        if ($file instanceof \Illuminate\Http\UploadedFile)
        {
            $handle = fopen($file->getRealPath(), 'r');
            $header = fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false)
            {
                $rowData = array_combine($header, $row);

                // convert any empty strings to null values
                foreach ($rowData as $key => $value) 
                {
                    if ($value === '') 
                    {
                        $rowData[$key] = null;
                        $rowData['domain'] = preg_replace('/^(http:\/\/|https:\/\/)?(www\.)?/', '', $rowData['domain']);
                    }
                }

                $validator = Validator::make($rowData, [
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

                if ($validator->passes())
                {
                    LinkSite::create($rowData);
                }
                else
                {
                    // didn't pass validation
                    dd($rowData);
                }

            }

            // Close the file handle
            fclose($handle);
            $file->delete();

        }
        else
        {
            // Handle the error appropriately
            // For example, you might want to set an error message or log the issue
        }
    }

}