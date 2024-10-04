<?php

namespace App\Filament\Admin\Widgets;

use App\Models\LinkSite;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NeedsMetricsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
            Stat::make('Needs SR AS', LinkSite::whereNull('semrush_AS')
                ->where('is_withdrawn', 0)->count()),
            Stat::make('Needs IP', LinkSite::whereNull('ip_address')
                ->where('is_withdrawn', 0)->count()),
            Stat::make('Needs Domain Age', LinkSite::whereNull('domain_creation_date')
                ->where('is_withdrawn', 0)->count()),
            Stat::make('Needs Majestic', LinkSite::whereNull('majestic_trust_flow')
                ->where('is_withdrawn', 0)
                ->where('semrush_AS', '>=', 20)->count()),

        ];
    }
}
