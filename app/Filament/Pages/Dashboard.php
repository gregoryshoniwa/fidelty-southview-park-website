<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CommitteeStats;
use App\Filament\Widgets\LatestInboxThreads;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            CommitteeStats::class,
            LatestInboxThreads::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
