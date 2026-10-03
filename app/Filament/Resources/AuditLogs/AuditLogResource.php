<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?int $navigationSort = 3;

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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('action')->badge(),
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('actor.name')->label('Actor')->placeholder(fn (AuditLog $record) => ucfirst((string) $record->actor_type)),
            TextEntry::make('subject')->state(fn (AuditLog $record) => $record->subject_type ? class_basename($record->subject_type).' #'.$record->subject_id : null)->placeholder('-'),
            TextEntry::make('ip')->label('IP address')->placeholder('-'),
            TextEntry::make('user_agent')->placeholder('-')->columnSpanFull(),
            KeyValueEntry::make('meta')
                ->state(fn (AuditLog $record) => collect($record->meta ?? [])->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v))->all())
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('actor:id,name'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i:s')->sortable(),
                TextColumn::make('action')->badge()->color(fn ($s) => str_contains((string) $s, 'deleted') ? 'danger' : 'gray')->searchable(),
                TextColumn::make('actor.name')->label('Actor')->placeholder(fn (AuditLog $r) => ucfirst((string) $r->actor_type))->searchable(),
                TextColumn::make('subject_type')->label('Subject')
                    ->formatStateUsing(fn ($s, AuditLog $r) => class_basename((string) $s).' #'.$r->subject_id)->placeholder('-'),
                TextColumn::make('ip')->label('IP')->placeholder('-')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all())
                    ->searchable()
                    ->multiple(),
                SelectFilter::make('actor_id')->label('Actor')->relationship('actor', 'name')->searchable(),
                Filter::make('created_at')
                    ->schema([DatePicker::make('from')->native(false), DatePicker::make('until')->native(false)])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($w, $d) => $w->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($w, $d) => $w->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
