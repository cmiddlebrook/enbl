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

        $header = ['Domain', 'Price $US', 'Rating', 'SEMRush AS', 'Traffic', 'Keywords', 'Ref Domains', 'Moz DA', 'Majestic TF', 'Majestic CF'];
        fputcsv($this->gsheet, $header);

        $sites = $this->getSitesToCheck();
        foreach ($sites as $linkSite)
        {
            $price = $this->calculatePrice($linkSite);
            if ($price > 25) continue; // stick to cheapie sites for now

            $row =
                [
                    $linkSite->domain,
                    $price,
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
        $thirdLowestPrice = $linkSite->third_lowest_price;
        $markup = max($thirdLowestPrice * 0.25, 10);
        $price = ceil($thirdLowestPrice + $markup);

        return $price;
    }

    private function calculateRating($linkSite)
    {
        $rating = 0;

        $rating += $this->calculate100BasedMetrics($linkSite->semrush_AS);
        $rating += $this->calculate100BasedMetrics($linkSite->moz_da);
        $rating += $this->calculate100BasedMetrics($linkSite->majestic_trust_flow);
        $rating += $this->calculate100BasedMetrics($linkSite->ahrefs_domain_rank);

        $rating += $this->calculateUnlimitedMetrics($linkSite->semrush_traffic, 4);
        $rating += $this->calculateUnlimitedMetrics($linkSite->semrush_organic_kw, 5);
        $rating += $this->calculateUnlimitedMetrics($linkSite->majestic_ref_domains, 6);

        return $rating;
    }

    private function calculate100BasedMetrics($value)
    {
        return round(pow(1.67, ($value / 10) - 1), 2);
    }

    private function calculateUnlimitedMetrics($value, $startingMultiplier)
    {
        return (round(min(($value / 500), 200), 2));
    }

    private function getSitesToCheck()
    {
        $sites = LinkSite::withThirdLowestPrice()
            ->where('is_withdrawn', 0)
            ->whereNotNull('semrush_traffic')
            ->whereNotNull('moz_da')
            ->has('sellers', '>=', 3)
            ->whereHas('niches', function ($query)
            {
                $query->where('niche_id', 1);
            })
            ->orderBy('semrush_organic_kw', 'desc')
            ->orderBy('majestic_trust_flow', 'desc')
            ->orderBy('semrush_AS', 'desc')
            ->get();


        return $sites;
    }
}
