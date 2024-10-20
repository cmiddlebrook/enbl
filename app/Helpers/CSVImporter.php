<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Models\LinkSite;
use App\Models\Seller;
use App\Models\SellerSite;
use Exception;

class CSVImporter
{
    private $numImported = 0;
    private $numErrors = 0;
    private $inputFile = null;
    private $errorFile = null;
    private $errorFilename = '';
    private $headerRow = null;
    private $isFileValid = false;
    private $csvFile = null;
    private $markSitesAsGeneral = false;

    public function importLinkSites($fileData)
    {
        if ($this->validateFile($fileData))
        {
            $this->importCSVFile($this->csvFile, 'makeLinkSiteValidator', 'processLinkSiteData');
        }
    }

    public function importSellerSites($fileData)
    {
        if ($this->validateFile($fileData))
        {
            $this->markSitesAsGeneral = $fileData['is_general'];
            $this->importCSVFile($this->csvFile, 'makeSellerSiteValidator', 'processSellerSiteData');
        }
    }

    private function validateFile($fileData)
    {
        $csvFileValue = $fileData['csv_file'];
        if (count($csvFileValue ?? []) == 1)
        {
            $liveWireFile = array_values($csvFileValue)[0];
            if ($liveWireFile instanceof UploadedFile)
            {
                $this->isFileValid = true;
                $this->csvFile = $liveWireFile;
                return true;
            }
        }

        return false;
    }

    private function initialiseFiles()
    {
        $this->errorFilename = 'downloads/csv_error_rows_' . time() . '_' . Str::random(4) . '.csv';
        $this->errorFile = fopen(public_path($this->errorFilename), 'w');

        $this->headerRow = fgetcsv($this->inputFile);
        $errorHeader = array_merge($this->headerRow, ['errors']);
        fputcsv($this->errorFile, $errorHeader);
    }

    private function importCSVFile($file, $validatorFunction, $dataProcessingFunction)
    {
        $this->inputFile = fopen($file->getRealPath(), 'r');
        $this->initialiseFiles();

        while (($row = fgetcsv($this->inputFile)) !== false)
        {
            $rowData = array_combine($this->headerRow, $row);
            $cleanedData = $this->cleanupDomains($rowData);

            $validator = $this->$validatorFunction($cleanedData);

            if ($validator->passes())
            {
                $this->$dataProcessingFunction($cleanedData);
                $this->numImported++;
            }
            else
            {
                $errorMessages = implode('; ', $validator->errors()->all());
                $errorRow = array_merge($row, [$errorMessages]);
                fputcsv($this->errorFile, $errorRow);
                $this->numErrors++;
            }
        }

        fclose($this->inputFile);
        fclose($this->errorFile);
        $file->delete();
    }

    private function processLinkSiteData($cleanedData)
    {
        $existingSite = LinkSite::where('domain', $cleanedData['domain'])->first();
        if ($existingSite)
        {
            $existingSite->update($cleanedData);
        }
        else
        {
            LinkSite::create($cleanedData);
        }
    }

    private function processSellerSiteData($cleanedData)
    {
        $seller = Seller::where('email', $cleanedData['email'])->first();
        $linkSite = LinkSite::where('domain', $cleanedData['domain'])->first();

        if (!$seller)
        {
            $seller = Seller::create($cleanedData);
        }

        if (!$linkSite)
        {
            $linkSite = LinkSite::create(['domain' => $cleanedData['domain']]);
        }

        Seller::where('id', $seller->id)->update(['last_import' => today()]);
        SellerSite::updateOrCreate(
            [
                'seller_id' => $seller->id,
                'link_site_id' => $linkSite->id
            ],
            [
                'price_guest_post' => $cleanedData['price_guest_post'],
                'price_link_insertion' => $cleanedData['price_link_insertion'],
            ]
        );

        if ($this->markSitesAsGeneral)
        {
            $linkSite->niches()->syncWithoutDetaching(1);
        }
    }


    private function cleanupDomains(array $rowData)
    {
        foreach ($rowData as $key => $value)
        {
            $rowData[$key] = trim($value);
            if ($value === '')
            {
                // convert any empty strings to null values
                $rowData[$key] = null;
            }

            // strip out unwanted domain prefixes
            $rowData['domain'] = trim(strtolower(preg_replace('/^(http:\/\/|https:\/\/)?(www\.)?|\/$/', '', $rowData['domain'])));
        }

        return $rowData;
    }

    private function getDomainValidationRule($data)
    {
        $existingSite = LinkSite::where('domain', $data['domain'])->first();
        return [
            'required',
            'regex:/^[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}$/',
            $existingSite ?
                Rule::unique('link_sites', 'domain')->ignore($existingSite->id) :
                'unique:link_sites,domain',
        ];
    }

    private function makeLinkSiteValidator($data)
    {
        $validator = Validator::make($data, [
            'domain' => $this->getDomainValidationRule($data),
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

    private function makeSellerSiteValidator($data)
    {
        $validator = Validator::make($data, [
            'domain' => $this->getDomainValidationRule($data),
            'email' => 'required|email',
            'email2' => 'nullable|email',
            'price_guest_post' => 'required|numeric|between:1,500',
            'price_link_insertion' => 'nullable|numeric'
        ]);

        return $validator;
    }

    public function isFileValid()
    {
        return $this->isFileValid;
    }

    public function getNumImported()
    {
        return $this->numImported;
    }

    public function getNumErrors()
    {
        return $this->numErrors;
    }

    public function getErrorFilename()
    {
        return $this->errorFilename;
    }
}
