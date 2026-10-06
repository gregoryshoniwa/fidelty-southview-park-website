<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Concerns\FinanceOnly;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Payment;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PaymentResource extends Resource
{
    use FinanceOnly;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 3;

    public const STATUSES = ['initiated' => 'Initiated', 'pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed'];

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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['resident.user:id,name', 'resident.stand:id,stand_number']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ulid')->label('Reference')->searchable()->copyable()->limit(12)->fontFamily('mono'),
                TextColumn::make('resident.user.name')->label('Resident')->placeholder('-')
                    ->description(fn (Payment $r) => $r->resident?->stand ? 'Stand '.$r->resident->stand->stand_number : null),
                TextColumn::make('biller_code')->label('Biller')
                    ->formatStateUsing(fn ($state) => Payment::BILLERS[$state]['label'] ?? $state)
                    ->description(fn (Payment $r) => $r->biller_reference),
                TextColumn::make('amount')->numeric(decimalPlaces: 2)->alignEnd(),
                TextColumn::make('platform_fee')->label('Fee')->numeric(decimalPlaces: 2)->alignEnd(),
                TextColumn::make('commission')->numeric(decimalPlaces: 2)->alignEnd(),
                TextColumn::make('total')->numeric(decimalPlaces: 2)->alignEnd()->weight('medium')->sortable(),
                TextColumn::make('currency'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? ucfirst((string) $state))
                    ->color(fn ($state) => match ($state) {
                        'paid' => 'success', 'failed' => 'danger', 'pending', 'initiated' => 'warning', default => 'gray'
                    }),
                TextColumn::make('paid_at')->dateTime('j M Y, H:i')->sortable()->placeholder('-'),
                TextColumn::make('gateway_reference')->label('Gateway ref')->toggleable(isToggledHiddenByDefault: true)->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES),
                SelectFilter::make('biller_code')->label('Biller')->options(collect(Payment::BILLERS)->map(fn ($b) => $b['label'])->all()),
                Filter::make('paid_at')
                    ->schema([DatePicker::make('from')->native(false), DatePicker::make('until')->native(false)])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($w, $d) => $w->whereDate('paid_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($w, $d) => $w->whereDate('paid_at', '<=', $d))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }
}
