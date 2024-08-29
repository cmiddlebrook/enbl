<?php

namespace App\Filament\Admin\Widgets;

use App\Models\LinkSite;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatBandsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
            Stat::make('Band 1', LinkSite::where('is_withdrawn', 0)
                ->has('sellers', '>=', 3)
                ->where('semrush_AS', '>=', 5)
                ->where('moz_da', '>=', 10)
                ->where('moz_pa', '>=', 10)
                ->where('majestic_trust_flow', '>=', 5)
                ->count()
            )->description('SR 5, DA 10, PA 10, TF 5'),
        ];
    }
}
