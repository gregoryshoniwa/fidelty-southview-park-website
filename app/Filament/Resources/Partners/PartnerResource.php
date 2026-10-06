<?php

namespace App\Filament\Resources\Partners;

use App\Filament\Resources\Partners\Pages\CreatePartner;
use App\Filament\Resources\Partners\Pages\EditPartner;
use App\Filament\Resources\Partners\Pages\ListPartners;
use App\Filament\Resources\Partners\RelationManagers\UsersRelationManager;
use App\Filament\Support\Uploads;
use App\Models\Partner;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

class PartnerResource extends Resource
{
    protected static ?string $model = Partner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Services and partners';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    /** Partners own requests, threads and invoices; deactivate instead of deleting. */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Organisation')->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255)->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true),
                Select::make('type')->options(Partner::TYPES)->required()->native(false),
                TextInput::make('website')->url()->maxLength(255),
                TextInput::make('contact_email')->email()->maxLength(255),
                TextInput::make('contact_phone')->tel()->maxLength(20)->regex('/^\+[1-9]\d{7,14}$/')->placeholder('+2637XXXXXXXX'),
                Uploads::image('logo_path', 'partners', 1024)->label('Logo')->imageEditor()->columnSpanFull(),
            ]),
            Section::make('Portal')->columnSpan(1)->schema([
                Toggle::make('active')->default(true),
                CheckboxList::make('modules')->label('Portal modules')->options(Partner::MODULES)->bulkToggleable(),
            ]),
            Section::make('Workflow')->columnSpanFull()->schema([
                TagsInput::make('workflow_steps')
                    ->label('Workflow steps (in order)')
                    ->placeholder('Add a step and press Enter')
                    ->reorderable()
                    ->helperText('Residents see these steps on their requests handled by this partner. Drag to reorder.'),
            ]),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('logo_path')->label('')->disk(Uploads::DISK)->height(32),
                TextColumn::make('name')->searchable()->sortable()->description(fn (Partner $r) => $r->website),
                TextColumn::make('type')->badge()->color('gray')->formatStateUsing(fn ($state) => Partner::TYPES[$state] ?? $state),
                TextColumn::make('users_count')->label('Users')->counts('users'),
                TextColumn::make('services_count')->label('Services')->counts('services'),
                TextColumn::make('contact_email')->label('Email')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->options(Partner::TYPES),
                TernaryFilter::make('active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [UsersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartners::route('/'),
            'create' => CreatePartner::route('/create'),
            'edit' => EditPartner::route('/{record}/edit'),
        ];
    }
}
