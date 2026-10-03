<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\FinanceOnly;
use App\Models\LedgerEntry;
use App\Services\LedgerService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LedgerOverview extends StatsOverviewWidget
{
    use FinanceOnly;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return static::isFinanceUser();
    }

    protected function getStats(): array
    {
        $ledger = app(LedgerService::class);
        $chain = $ledger->verifyChain();
        $pending = LedgerEntry::where('status', 'pending')->count();

        return [
            Stat::make('Balance (USD)', '$'.number_format($ledger->balance('USD'), 2))
                ->description('Posted entries only')
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Chain integrity', $chain['ok'] ? 'OK' : 'BROKEN at #'.$chain['broken_at'])
                ->description($chain['ok'] ? 'Every entry matches its SHA-256 hash chain' : 'An entry was altered outside the application. Investigate now.')
                ->icon($chain['ok'] ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedExclamationTriangle)
                ->color($chain['ok'] ? 'success' : 'danger'),
            Stat::make('Awaiting approval', number_format($pending))
                ->description('Second signatory required')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color($pending ? 'warning' : 'gray'),
        ];
    }
}
