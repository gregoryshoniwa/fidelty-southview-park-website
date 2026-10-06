<?php

namespace App\Filament\Resources\LedgerEntries;

use App\Filament\Concerns\FinanceOnly;
use App\Filament\Resources\LedgerEntries\Pages\ListLedgerEntries;
use App\Filament\Widgets\LedgerOverview;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Services\LedgerService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LedgerEntryResource extends Resource
{
    use FinanceOnly;

    protected static ?string $model = LedgerEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Ledger';

    protected static ?string $modelLabel = 'ledger entry';

    protected static ?string $pluralModelLabel = 'ledger';

    protected static ?int $navigationSort = 2;

    public const STATUSES = ['posted' => 'Posted', 'pending' => 'Pending approval'];

    /** The ledger is append-only: entries are posted through LedgerService and corrected by reversal. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Amounts must be passed to LedgerService::post() as 2-decimal strings: the hash is computed over
     * (string) amount at post time and verified against the decimal:2 cast ("-12.50"), so a float
     * ("-12.5") would break the chain.
     */
    public static function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Same entry as LedgerService::reverse(), but with a correctly formatted amount.
     * (LedgerService::reverse() passes a float, which breaks verifyChain(); see report.)
     */
    public static function reverse(LedgerEntry $entry, string $reason): LedgerEntry
    {
        return app(LedgerService::class)->post([
            'type' => 'adjustment',
            'description' => 'Reversal of #'.$entry->id.': '.$reason,
            'amount' => self::money(-1 * (float) $entry->amount),
            'currency' => $entry->currency,
            'source' => 'treasurer',
            'created_by' => auth()->id(),
            'reverses_id' => $entry->id,
            'service_id' => $entry->service_id,
            'partner_id' => $entry->partner_id,
        ]);
    }

    public static function getWidgets(): array
    {
        return [LedgerOverview::class];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['partner:id,name', 'creator:id,name', 'approver:id,name']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('entry_date')->label('Date')->date('j M Y')->sortable(),
                TextColumn::make('type')->badge()
                    ->formatStateUsing(fn ($state) => LedgerEntry::TYPES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'commission', 'fee', 'advertising' => 'success',
                        'expense', 'payout' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('description')->searchable()->wrap()->limit(70),
                TextColumn::make('partner.name')->label('Partner')->placeholder('-')->toggleable(),
                TextColumn::make('amount')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium')
                    ->color(fn ($state) => (float) $state < 0 ? 'danger' : 'success'),
                TextColumn::make('currency'),
                TextColumn::make('source')->badge()->color('gray')->toggleable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'posted' ? 'success' : 'warning'),
                TextColumn::make('creator.name')->label('Created by')->placeholder('System')->toggleable(),
                TextColumn::make('approver.name')->label('Approved by')->placeholder('-')->toggleable()
                    ->description(fn (LedgerEntry $r) => $r->approved_at?->format('j M Y')),
                TextColumn::make('reverses_id')->label('Reverses')->formatStateUsing(fn ($state) => $state ? '#'.$state : null)->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->options(LedgerEntry::TYPES)->multiple(),
                SelectFilter::make('status')->options(self::STATUSES),
                SelectFilter::make('currency')->options(['USD' => 'USD', 'ZWG' => 'ZWG']),
                Filter::make('entry_date')
                    ->schema([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($w, $d) => $w->whereDate('entry_date', '>=', $d))
                        ->when($data['until'] ?? null, fn ($w, $d) => $w->whereDate('entry_date', '<=', $d)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'From '.$data['from'] : null,
                        ($data['until'] ?? null) ? 'Until '.$data['until'] : null,
                    ])),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (LedgerEntry $record) => $record->status === 'pending' && $record->created_by !== auth()->id())
                    ->requiresConfirmation()
                    ->modalDescription('Second signatory approval. The entry is posted and counts towards the balance.')
                    ->action(function (LedgerEntry $record) {
                        abort_if($record->created_by === auth()->id(), 403, 'The creator cannot approve their own entry.');
                        $record->update(['status' => 'posted', 'approved_by' => auth()->id(), 'approved_at' => now()]);
                        AuditLog::record('ledger.approved', $record);
                        Notification::make()->title('Entry approved and posted')->success()->send();
                    }),
                Action::make('reverse')
                    ->label('Reverse')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (LedgerEntry $record) => $record->type !== 'adjustment' && $record->status === 'posted'
                        && ! LedgerEntry::where('reverses_id', $record->id)->exists())
                    ->modalDescription('Posts a new adjustment entry for the opposite amount. The original entry is kept.')
                    ->schema([
                        Textarea::make('reason')->required()->maxLength(200)->rows(2),
                    ])
                    ->action(function (LedgerEntry $record, array $data) {
                        $rev = self::reverse($record, $data['reason']);
                        AuditLog::record('ledger.reversed', $record, ['reversal_id' => $rev->id, 'reason' => $data['reason']]);
                        Notification::make()->title('Reversal #'.$rev->id.' posted')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListLedgerEntries::route('/')];
    }
}
