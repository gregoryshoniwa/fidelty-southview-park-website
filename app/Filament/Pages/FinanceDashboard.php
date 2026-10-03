<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\FinanceOnly;
use App\Filament\Widgets\FinanceStats;
use App\Filament\Widgets\LedgerOverview;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class FinanceDashboard extends BaseDashboard
{
    use FinanceOnly;

    protected static string $routePath = '/finance';

    protected static ?string $title = 'Finance overview';

    protected static ?string $navigationLabel = 'Overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public function getWidgets(): array
    {
        return [FinanceStats::class, LedgerOverview::class];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
