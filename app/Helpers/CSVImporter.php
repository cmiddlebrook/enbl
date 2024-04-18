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

                // validate here
                $validator = Validator::make($rowData, [
                    'domain' => 'required',
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