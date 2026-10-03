<?php

namespace App\Filament\Resources\CommunityPages;

use App\Filament\Resources\CommunityPages\Pages\CreateCommunityPage;
use App\Filament\Resources\CommunityPages\Pages\EditCommunityPage;
use App\Filament\Resources\CommunityPages\Pages\ListCommunityPages;
use App\Filament\Resources\CommunityPages\RelationManagers\PostsRelationManager;
use App\Filament\Support\Uploads;
use App\Models\CommunityPage;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Support\Str;
use UnitEnum;

class CommunityPageResource extends Resource
{
    protected static ?string $model = CommunityPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Community pages';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page')->columnSpan(2)->columns(2)->schema([
                Select::make('type')->options(CommunityPage::TYPES)->required()->native(false),
                TextInput::make('name')->required()->maxLength(255)->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('tagline')->maxLength(160),
                Textarea::make('description')->rows(5)->columnSpanFull(),
                Uploads::image('logo_path', 'pages', 1024)->label('Logo')->imageEditor(),
                Uploads::image('cover_path', 'pages', 2048)->label('Cover image')->imageEditor()->automaticallyResizeImagesToWidth('1600'),
            ]),
            Section::make('Contact and status')->columnSpan(1)->schema([
                TextInput::make('phone')->tel()->maxLength(20),
                TextInput::make('address')->maxLength(255),
                TagsInput::make('hours')->placeholder('e.g. Mon-Fri 08:00-17:00')->reorderable(),
                Select::make('partner_id')->label('Linked partner')->options(fn () => \App\Models\Partner::orderBy('name')->pluck('name', 'id'))->searchable(),
                Select::make('owner_user_id')->label('Owner')->relationship('owner', 'name')->searchable()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name.' ('.$record->phone.')'),
                Toggle::make('verified')->label('Partner (managed by the organisation)'),
                Toggle::make('active')->default(true),
            ]),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('logo_path')->label('')->disk(Uploads::DISK)->circular()->height(32),
                TextColumn::make('name')->searchable()->sortable()->description(fn (CommunityPage $r) => $r->tagline),
                TextColumn::make('type')->badge()->color('gray')->formatStateUsing(fn ($s) => CommunityPage::TYPES[$s] ?? $s),
                IconColumn::make('verified')->label('Partner')->boolean(),
                TextColumn::make('posts_count')->label('Posts')->counts('posts'),
                TextColumn::make('reported_posts')->label('Reported posts')
                    ->state(fn (CommunityPage $r) => $r->posts()->where('reported_count', '>', 0)->whereNull('hidden_at')->count())
                    ->badge()->color(fn ($s) => $s > 0 ? 'danger' : 'gray'),
                IconColumn::make('active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->options(CommunityPage::TYPES),
                TernaryFilter::make('verified')->label('Partner page'),
                TernaryFilter::make('active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [PostsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityPages::route('/'),
            'create' => CreateCommunityPage::route('/create'),
            'edit' => EditCommunityPage::route('/{record}/edit'),
        ];
    }
}
