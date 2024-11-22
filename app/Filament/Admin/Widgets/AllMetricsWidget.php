<?php

namespace App\Filament\Admin\Widgets;

use App\Models\LinkSite;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AllMetricsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
            Stat::make(
                'All Metrics',
                LinkSite::where('is_withdrawn', 0)
                    ->whereNotNull('semrush_AS')
                    ->whereNotNull('ip_address')
                    ->whereNotNull('domain_creation_date')
                    ->whereNotNull('moz_da')
                    ->whereNotNull('majestic_trust_flow')
                    // ->whereNotNull('ahrefs_domain_rank')
                    ->count()
            ), //->description('All metrics'),
        ];
    }
}
