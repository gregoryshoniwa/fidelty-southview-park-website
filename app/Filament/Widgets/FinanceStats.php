<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\FinanceOnly;
use App\Models\LedgerEntry;
use App\Services\LedgerService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceStats extends StatsOverviewWidget
{
    use FinanceOnly;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return static::isFinanceUser();
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $month = LedgerEntry::where('status', 'posted')->where('currency', 'USD')
            ->whereBetween('entry_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);

        $income = (float) (clone $month)->where('amount', '>', 0)->sum('amount');
        $expenses = (float) (clone $month)->where('type', 'expense')->sum('amount');
        $pendingPayouts = LedgerEntry::where('type', 'payout')->where('status', 'pending')->count();

        return [
            Stat::make('Balance (USD)', '$'.number_format(app(LedgerService::class)->balance('USD'), 2))
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Income this month', '$'.number_format($income, 2))
                ->description(now()->format('F Y'))
                ->icon(Heroicon::OutlinedArrowTrendingUp)
                ->color('success'),
            Stat::make('Expenses this month', '$'.number_format(abs($expenses), 2))
                ->description(now()->format('F Y'))
                ->icon(Heroicon::OutlinedArrowTrendingDown)
                ->color('danger'),
            Stat::make('Pending payouts', number_format($pendingPayouts))
                ->description('Awaiting a second signatory')
                ->icon(Heroicon::OutlinedClock)
                ->color($pendingPayouts ? 'warning' : 'gray'),
        ];
    }
}
