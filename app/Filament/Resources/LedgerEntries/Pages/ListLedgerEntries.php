<?php

namespace App\Filament\Resources\LedgerEntries\Pages;

use App\Filament\Resources\LedgerEntries\LedgerEntryResource;
use App\Filament\Widgets\LedgerOverview;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Services\LedgerService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListLedgerEntries extends ListRecords
{
    protected static string $resource = LedgerEntryResource::class;

    protected function getHeaderWidgets(): array
    {
        return [LedgerOverview::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordExpense')
                ->label('Record expense')
                ->icon(Heroicon::OutlinedMinusCircle)
                ->modalDescription('Posted immediately as a negative entry. A receipt is required.')
                ->schema([
                    TextInput::make('description')->required()->maxLength(255),
                    TextInput::make('amount')->label('Amount (positive)')->required()->numeric()->minValue(0.01)->maxValue(1000000)->step('0.01'),
                    Select::make('currency')->options(['USD' => 'USD', 'ZWG' => 'ZWG'])->default('USD')->required(),
                    DatePicker::make('entry_date')->label('Date')->default(today())->maxDate(today())->required()->native(false),
                    FileUpload::make('receipt_path')
                        ->label('Receipt')
                        ->required()
                        ->disk('local')
                        ->directory('receipts')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(5120),
                ])
                ->action(function (array $data) {
                    $entry = app(LedgerService::class)->post([
                        'entry_date' => $data['entry_date'],
                        'type' => 'expense',
                        'description' => $data['description'],
                        'amount' => LedgerEntryResource::money(-1 * abs((float) $data['amount'])),
                        'currency' => $data['currency'],
                        'source' => 'treasurer',
                        'receipt_path' => $data['receipt_path'],
                        'created_by' => auth()->id(),
                    ]);
                    AuditLog::record('ledger.expense_recorded', $entry);
                    Notification::make()->title('Expense #'.$entry->id.' posted')->success()->send();
                }),
            Action::make('requestPayout')
                ->label('Request payout')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('gray')
                ->modalDescription('Payouts need a second signatory: another finance admin must approve before it is posted.')
                ->schema([
                    TextInput::make('description')->required()->maxLength(255),
                    Select::make('partner_id')->label('Partner (optional)')->options(fn () => Partner::orderBy('name')->pluck('name', 'id'))->searchable(),
                    TextInput::make('amount')->label('Amount (positive)')->required()->numeric()->minValue(0.01)->maxValue(1000000)->step('0.01'),
                    Select::make('currency')->options(['USD' => 'USD', 'ZWG' => 'ZWG'])->default('USD')->required(),
                ])
                ->action(function (array $data) {
                    $entry = app(LedgerService::class)->post([
                        'type' => 'payout',
                        'description' => $data['description'],
                        'partner_id' => $data['partner_id'] ?? null,
                        'amount' => LedgerEntryResource::money(-1 * abs((float) $data['amount'])),
                        'currency' => $data['currency'],
                        'source' => 'treasurer',
                        'status' => 'pending',
                        'created_by' => auth()->id(),
                    ]);
                    AuditLog::record('ledger.payout_requested', $entry);
                    Notification::make()->title('Payout #'.$entry->id.' awaiting approval')->success()->send();
                }),
        ];
    }
}
