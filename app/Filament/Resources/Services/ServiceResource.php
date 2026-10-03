<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Support\Uploads;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Services and partners';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public const FEE_TYPES = ['none' => 'Free', 'flat' => 'Flat fee', 'percent' => 'Percentage', 'partner_paid' => 'Paid by partner'];

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Service')->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255)->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true)
                    ->helperText('Web address: /services/{slug}'),
                Textarea::make('summary')->required()->rows(2)->maxLength(300)->columnSpanFull(),
                Uploads::richEditor('body'),
                TagsInput::make('steps')
                    ->label('Steps')
                    ->placeholder('Add a step and press Enter')
                    ->reorderable()
                    ->helperText('Shown to residents in order. A partner\'s own workflow steps take precedence.')
                    ->columnSpanFull(),
            ]),
            Section::make('Settings')->columnSpan(1)->schema([
                TextInput::make('icon')->required()->default('circle')->maxLength(40)
                    ->helperText('Lucide icon name, e.g. "file-text", "shield", "wallet".'),
                Select::make('partner_id')->label('Partner')->relationship('partner', 'name')->searchable()->preload()->placeholder('The association'),
                Select::make('phase')->options([1 => 'Phase 1', 2 => 'Phase 2', 3 => 'Phase 3'])->default(1)->required(),
                TextInput::make('sort')->numeric()->minValue(0)->default(0),
                Toggle::make('enabled')->default(true),
                Toggle::make('requires_verification')->label('Requires a verified stand')->default(true),
            ]),
            Section::make('Fees')->columnSpan(1)->schema([
                Select::make('fee_type')->options(self::FEE_TYPES)->default('none')->required()->live(),
                TextInput::make('fee_amount')->numeric()->minValue(0)->default(0)
                    ->suffix(fn (Get $get) => $get('fee_type') === 'percent' ? '%' : null)
                    ->visible(fn (Get $get) => in_array($get('fee_type'), ['flat', 'percent'], true)),
                Select::make('fee_currency')->options(['USD' => 'USD', 'ZWG' => 'ZWG'])->default('USD')->required()
                    ->visible(fn (Get $get) => $get('fee_type') === 'flat'),
                TextInput::make('commission_percent')->label('Association commission')->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%'),
            ]),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (Service $r) => '/services/'.$r->slug),
                TextColumn::make('partner.name')->label('Partner')->placeholder('Association'),
                TextColumn::make('phase')->badge()->color('gray')->formatStateUsing(fn ($s) => 'Phase '.$s)->sortable(),
                TextColumn::make('fee')->label('Fee')->state(fn (Service $r) => $r->feeLabel()),
                TextColumn::make('commission_percent')->label('Commission')->suffix('%')->toggleable(),
                ToggleColumn::make('enabled'),
            ])
            ->filters([
                SelectFilter::make('phase')->options([1 => 'Phase 1', 2 => 'Phase 2', 3 => 'Phase 3']),
                SelectFilter::make('partner_id')->label('Partner')->relationship('partner', 'name'),
                TernaryFilter::make('enabled'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
