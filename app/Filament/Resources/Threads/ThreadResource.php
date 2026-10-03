<?php

namespace App\Filament\Resources\Threads;

use App\Filament\Resources\Threads\Pages\ListThreads;
use App\Filament\Resources\Threads\Pages\ViewThread;
use App\Models\Thread;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ThreadResource extends Resource
{
    protected static ?string $model = Thread::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Inbox';

    protected static ?string $navigationLabel = 'Committee inbox';

    protected static ?string $modelLabel = 'inbox thread';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?int $navigationSort = 1;

    public const STATUSES = ['open' => 'Open', 'answered' => 'Answered', 'closed' => 'Closed'];

    /** Committee inbox only: partner threads belong to the partner portal. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('partner_id');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::getEloquentQuery()->where('status', 'open')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

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

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'open' => 'warning',
            'answered' => 'success',
            default => 'gray',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Thread')
                ->columns(3)
                ->schema([
                    TextEntry::make('reference')->copyable(),
                    TextEntry::make('subject')->columnSpan(2),
                    TextEntry::make('category')->badge()->formatStateUsing(fn ($state) => Thread::CATEGORIES[$state] ?? $state),
                    TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)->color(fn ($state) => self::statusColor($state)),
                    TextEntry::make('assignee.name')->label('Assigned to')->placeholder('Unassigned'),
                    TextEntry::make('resident.user.name')->label('Resident'),
                    TextEntry::make('resident.stand.stand_number')->label('Stand')->placeholder('-'),
                    TextEntry::make('request.reference')->label('Service request')->placeholder('-'),
                    TextEntry::make('created_at')->label('Opened')->dateTime(),
                    TextEntry::make('first_response_at')->label('First response')->dateTime()->placeholder('Not yet answered'),
                    TextEntry::make('last_message_at')->label('Last message')->since(),
                ]),
            Section::make('Conversation')
                ->schema([
                    ViewEntry::make('messages')
                        ->hiddenLabel()
                        ->view('filament.threads.conversation'),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['resident.user', 'resident.stand', 'assignee']))
            ->defaultSort('last_message_at', 'desc')
            ->columns([
                TextColumn::make('reference')->searchable()->copyable()->weight('medium'),
                TextColumn::make('subject')->searchable()->limit(50)->wrap(),
                TextColumn::make('category')->badge()->color('gray')->formatStateUsing(fn ($state) => Thread::CATEGORIES[$state] ?? $state),
                TextColumn::make('resident.user.name')
                    ->label('Resident')
                    ->searchable()
                    ->description(fn (Thread $r) => $r->resident?->stand ? 'Stand '.$r->resident->stand->stand_number : null),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)->color(fn ($state) => self::statusColor($state)),
                TextColumn::make('last_message_at')->label('Last message')->since()->sortable(),
                TextColumn::make('assignee.name')->label('Assigned to')->placeholder('Unassigned')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES),
                SelectFilter::make('category')->options(Thread::CATEGORIES),
                SelectFilter::make('assigned_to')->label('Assigned to')->relationship('assignee', 'name'),
            ])
            ->recordActions([
                ViewAction::make()->label('Open'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListThreads::route('/'),
            'view' => ViewThread::route('/{record}'),
        ];
    }
}
