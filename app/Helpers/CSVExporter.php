<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use App\Models\LinkSite;
use App\Models\Seller;
use App\Models\SellerSite;
use Carbon\Carbon;
use Exception;

class CSVExporter
{
    private $gsheetFilename = '';
    private $gsheet = null;

    public function exportGoogleSheetCSV()
    {
        $timestamp = Carbon::now()->format('Y-m-d-H-i');
        $this->gsheetFilename = 'downloads/csv_google_sheet_' . $timestamp . '_' . Str::random(4) . '.csv';
        $this->gsheet = fopen(public_path($this->gsheetFilename), 'w');

        $header = ['Domain', 'Price $US', 'Rating', 'SEMRush Score', 'Traffic', 'Keywords', 'Ref Domains', 'Moz DA', 'Majestic TF', 'Majestic CF'];
        fputcsv($this->gsheet, $header);

        $sites = $this->getSitesToCheck();
        foreach ($sites as $linkSite)
        {
            $row =
                [
                    $linkSite->domain,
                    $this->calculatePrice($linkSite),
                    $this->calculateRating($linkSite),
                    $linkSite->semrush_AS,
                    $linkSite->semrush_traffic,
                    $linkSite->semrush_organic_kw,
                    $linkSite->majestic_ref_domains,
                    $linkSite->moz_da,
                    $linkSite->majestic_trust_flow,
                    $linkSite->majestic_citation_flow,
                ];

            fputcsv($this->gsheet, $row);
        }

        fclose($this->gsheet);
    }

    public function getGSheetFilename()
    {
        return $this->gsheetFilename;
    }

    private function calculatePrice($linkSite)
    {
        $avgLowPrice = $linkSite->avg_low_price;
        $markup = max($avgLowPrice * 0.25, 10);
        $price = ceil($avgLowPrice + $markup);

        return $price;
    }

    private function calculateRating($linkSite)
    {
        $rating = 0;

        $rating += $this->calculate100BasedMetrics($linkSite->semrush_AS);
        $rating += $this->calculate100BasedMetrics($linkSite->moz_da);
        $rating += $this->calculate100BasedMetrics($linkSite->majestic_trust_flow);

        $rating += $this->calculateUnlimitedMetrics($linkSite->semrush_traffic, 4);
        $rating += $this->calculateUnlimitedMetrics($linkSite->semrush_organic_kw, 5);
        $rating += $this->calculateUnlimitedMetrics($linkSite->majestic_ref_domains, 6);

        return $rating;
    }

    private function calculate100BasedMetrics($value)
    {
        $rating = 0;

        for ($step = 10; $step <= 100; $step += 10)
        {
            if ($value >= $step)
            {
                $rating += $step / 10;
            }
        }

        return $rating;
    }

    private function calculateUnlimitedMetrics($value, $startingMultiplier)
    {
        $rating = 0;
        $step = 10; 
        $multiplier = $startingMultiplier; 

        while ($step <= 100000000) // 100 million
        {
            if ($value >= $step)
            {
                $rating += $multiplier;
            }

            // 100, 1k, 10k, 100k, 1m, 10m, 100m
            $step *= 10;
            $multiplier++;
        }

        return $rating;
    }

    private function getSitesToCheck()
    {
        $sites = LinkSite::withAvgLowPrices()->withLowestPrice()
            ->where('is_withdrawn', 0)
            ->has('sellers', '>=', 4)
            ->whereNotNull('semrush_traffic')
            ->whereNotNull('moz_da')
            ->orderBy('semrush_organic_kw', 'desc')
            ->orderBy('majestic_trust_flow', 'desc')
            ->orderBy('semrush_AS', 'desc')
            ->get();

        return $sites;
    }
}
