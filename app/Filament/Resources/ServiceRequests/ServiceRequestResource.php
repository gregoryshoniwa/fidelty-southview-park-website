<?php

namespace App\Filament\Resources\ServiceRequests;

use App\Filament\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Models\Document;
use App\Models\RequestEvent;
use App\Models\ServiceRequest;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
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

class ServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Services and partners';

    protected static ?string $navigationLabel = 'Service requests';

    protected static ?string $recordTitleAttribute = 'reference';

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

    public static function statusColor(?string $s): string
    {
        return match ($s) {
            'open', 'waiting_partner' => 'info',
            'waiting_resident', 'waiting_payment' => 'warning',
            'approved', 'closed' => 'success',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public static function stepLabel(ServiceRequest $r): string
    {
        $steps = $r->stepsList();
        $name = $steps[$r->step - 1] ?? null;

        return $steps ? "{$r->step} of ".count($steps).($name ? ": {$name}" : '') : (string) $r->step;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request')->columns(3)->schema([
                TextEntry::make('reference')->copyable(),
                TextEntry::make('service.name')->label('Service'),
                TextEntry::make('partner.name')->label('Partner')->placeholder('Association'),
                TextEntry::make('resident.user.name')->label('Resident'),
                TextEntry::make('resident.stand.stand_number')->label('Stand')->placeholder('-'),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($s) => ServiceRequest::STATUSES[$s] ?? $s)->color(fn ($s) => self::statusColor($s)),
                TextEntry::make('step')->state(fn (ServiceRequest $record) => self::stepLabel($record)),
                TextEntry::make('created_at')->label('Opened')->dateTime(),
                TextEntry::make('closed_at')->dateTime()->placeholder('-'),
            ]),
            Section::make('Timeline')->schema([
                RepeatableEntry::make('events')
                    ->hiddenLabel()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('created_at')->label('When')->dateTime('j M Y, H:i'),
                        TextEntry::make('type')->badge()->color('gray')->formatStateUsing(fn ($s) => str($s)->replace('_', ' ')->ucfirst()),
                        TextEntry::make('actor_type')->label('By')->formatStateUsing(fn ($s) => str($s)->replace('_', ' ')->ucfirst()),
                        TextEntry::make('payload')->label('Details')
                            ->state(fn (RequestEvent $record) => self::eventSummary($record))
                            ->placeholder('-'),
                    ])
                    ->placeholder('No events yet.'),
            ]),
            Section::make('Documents')->description('Names only. Files are available to the resident and the assigned partner.')->schema([
                RepeatableEntry::make('documents')
                    ->hiddenLabel()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('original_name')->label('File'),
                        TextEntry::make('kind')->formatStateUsing(fn ($s) => Document::KINDS[$s] ?? $s),
                        TextEntry::make('created_at')->label('Uploaded')->dateTime('j M Y, H:i'),
                    ])
                    ->placeholder('No documents.'),
            ]),
        ])->columns(1);
    }

    public static function eventSummary(RequestEvent $e): ?string
    {
        $p = $e->payload ?? [];

        return match ($e->type) {
            'opened' => isset($p['service']) ? 'Opened for '.$p['service'] : null,
            'status_change' => trim(implode(' ', array_filter([
                isset($p['to']['status']) ? 'Status: '.(ServiceRequest::STATUSES[$p['to']['status']] ?? $p['to']['status']).'.' : null,
                isset($p['to']['step']) ? 'Step '.$p['to']['step'].'.' : null,
                $p['note'] ?? null,
            ]))) ?: null,
            'document_added' => isset($p['kind']) ? (Document::KINDS[$p['kind']] ?? $p['kind']) : null,
            default => collect($p)->filter(fn ($v) => is_scalar($v))->map(fn ($v, $k) => "$k: $v")->implode(', ') ?: null,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['service', 'partner', 'resident.user', 'resident.stand']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->searchable()->copyable(),
                TextColumn::make('service.name')->label('Service')->searchable(),
                TextColumn::make('partner.name')->label('Partner')->placeholder('Association')->toggleable(),
                TextColumn::make('resident.user.name')->label('Resident')->searchable(),
                TextColumn::make('resident.stand.stand_number')->label('Stand')->searchable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($s) => ServiceRequest::STATUSES[$s] ?? $s)->color(fn ($s) => self::statusColor($s)),
                TextColumn::make('step')->state(fn (ServiceRequest $r) => self::stepLabel($r))->limit(40),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ServiceRequest::STATUSES)->multiple(),
                SelectFilter::make('service_id')->label('Service')->relationship('service', 'name'),
                SelectFilter::make('partner_id')->label('Partner')->relationship('partner', 'name'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceRequests::route('/'),
            'view' => ViewServiceRequest::route('/{record}'),
        ];
    }
}
