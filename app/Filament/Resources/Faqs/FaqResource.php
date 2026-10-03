<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Models\Faq;
use App\Models\Service;
use App\Services\AssistantService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $modelLabel = 'FAQ';

    protected static ?string $pluralModelLabel = 'FAQs';

    protected static ?string $recordTitleAttribute = 'question';

    protected static ?int $navigationSort = 3;

    public static function topics(): array
    {
        return ['general' => 'General'] + Service::orderBy('sort')->pluck('name', 'slug')->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('question')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('answer')->required()->rows(6)->columnSpanFull()
                ->helperText('Plain text. The assistant also uses this answer.'),
            Select::make('topic')->options(fn () => self::topics())->default('general')->required()->searchable(),
            TextInput::make('sort')->numeric()->minValue(0)->default(0),
            Toggle::make('published')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        $rebuild = fn () => app(AssistantService::class)->rebuildKnowledge();

        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('sort')->label('#')->sortable(),
                TextColumn::make('question')->searchable()->wrap()->limit(90),
                TextColumn::make('topic')->badge()->color('gray')->formatStateUsing(fn ($s) => self::topics()[$s] ?? $s),
                IconColumn::make('published')->boolean(),
            ])
            ->filters([
                SelectFilter::make('topic')->options(fn () => self::topics()),
                TernaryFilter::make('published'),
            ])
            ->recordActions([
                EditAction::make()->after($rebuild),
                DeleteAction::make()->after($rebuild),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFaqs::route('/')];
    }
}
