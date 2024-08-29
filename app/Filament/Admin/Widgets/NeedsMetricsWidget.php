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
            Stat::make('Needs SEMRush', LinkSite::where('semrush_AS', '=', null)->count()),
            Stat::make('Needs Country', LinkSite::where('country_code', '=', null)->count()),
            Stat::make('Needs Majestic', LinkSite::where('majestic_trust_flow', '=', null)->count()),
        ];
    }
}
