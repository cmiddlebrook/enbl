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
            Stat::make('Needs SEMRush', LinkSite::whereNull('semrush_AS')->count()),
            Stat::make('Needs Country', LinkSite::whereNull('country_code')->count()),
            Stat::make('Needs Domain Age', LinkSite::whereNull('domain_creation_date')->count()),
            Stat::make('Needs Majestic', LinkSite::whereNull('majestic_trust_flow')->count()),

        ];
    }
}
