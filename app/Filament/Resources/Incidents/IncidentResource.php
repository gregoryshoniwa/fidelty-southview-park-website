<?php

namespace App\Filament\Resources\Incidents;

use App\Filament\Resources\Incidents\Pages\ListIncidents;
use App\Filament\Resources\Incidents\Pages\ViewIncident;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Services\NotificationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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

class IncidentResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Inbox';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?int $navigationSort = 2;

    public const STATUSES = ['open' => 'Open', 'responding' => 'Responding', 'resolved' => 'Resolved'];

    public static function getNavigationBadge(): ?string
    {
        $n = Incident::where('status', 'open')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
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

    public static function statusColor(?string $s): string
    {
        return match ($s) {
            'open' => 'danger',
            'responding' => 'warning',
            'resolved' => 'success',
            default => 'gray',
        };
    }

    public static function updateStatusAction(): Action
    {
        return Action::make('updateStatus')
            ->label('Update status')
            ->icon(Heroicon::OutlinedArrowPath)
            ->fillForm(fn (Incident $record) => ['status' => $record->status])
            ->schema([
                Select::make('status')->options(self::STATUSES)->required()->native(false),
            ])
            ->action(function (Incident $record, array $data) {
                $old = $record->status;
                $record->update(['status' => $data['status']]);
                AuditLog::record('admin.incident.status_updated', $record, ['from' => $old, 'to' => $data['status']]);
                if ($old !== $data['status'] && $record->resident?->user) {
                    app(NotificationService::class)->notify($record->resident->user, 'Incident '.$record->reference.' is '.$data['status'], 'The committee updated your report.', '/app/security', 'incident');
                }
                Notification::make()->title('Status updated')->success()->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('reference')->copyable(),
                TextEntry::make('category')->badge()->color('gray'),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($s) => self::STATUSES[$s] ?? $s)->color(fn ($s) => self::statusColor($s)),
                TextEntry::make('resident.user.name')->label('Resident'),
                TextEntry::make('resident.stand.stand_number')->label('Stand')->placeholder('-'),
                TextEntry::make('partner.name')->label('Security partner')->placeholder('None'),
                TextEntry::make('location')->placeholder('-'),
                TextEntry::make('created_at')->label('Reported')->dateTime(),
                TextEntry::make('updated_at')->label('Updated')->since(),
                TextEntry::make('description')->columnSpanFull()->extraAttributes(['style' => 'white-space:pre-wrap']),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['resident.user', 'resident.stand', 'partner']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->searchable()->copyable(),
                TextColumn::make('category')->badge()->color('gray')->searchable(),
                TextColumn::make('location')->limit(30)->placeholder('-'),
                TextColumn::make('resident.user.name')->label('Resident')->searchable()
                    ->description(fn (Incident $r) => $r->resident?->stand ? 'Stand '.$r->resident->stand->stand_number : null),
                TextColumn::make('partner.name')->label('Partner')->placeholder('-')->toggleable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($s) => self::STATUSES[$s] ?? $s)->color(fn ($s) => self::statusColor($s)),
                TextColumn::make('created_at')->label('Reported')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES),
                SelectFilter::make('partner_id')->label('Partner')->relationship('partner', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                self::updateStatusAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncidents::route('/'),
            'view' => ViewIncident::route('/{record}'),
        ];
    }
}
