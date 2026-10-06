<?php

namespace App\Filament\Resources\Polls;

use App\Filament\Resources\Polls\Pages\CreatePoll;
use App\Filament\Resources\Polls\Pages\EditPoll;
use App\Filament\Resources\Polls\Pages\ListPolls;
use App\Filament\Resources\Polls\Pages\ViewPoll;
use App\Models\Poll;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PollResource extends Resource
{
    protected static ?string $model = Poll::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $recordTitleAttribute = 'question';

    protected static ?int $navigationSort = 2;

    public static function stateOf(Poll $p): string
    {
        return match (true) {
            now()->lt($p->opens_at) => 'Scheduled',
            now()->lte($p->closes_at) => 'Open',
            default => 'Closed',
        };
    }

    /** Votes are tied to option positions, so a poll with votes cannot be deleted or have its options changed. */
    public static function canDelete(Model $record): bool
    {
        return ! $record->votes()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('question')->required()->maxLength(255)->columnSpanFull(),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                TagsInput::make('options')
                    ->required()
                    ->reorderable()
                    ->rules(['array', 'min:2', 'max:10'])
                    ->validationMessages(['min' => 'Add at least two options.'])
                    ->placeholder('Add an option and press Enter')
                    ->disabled(fn (?Poll $record) => $record?->votes()->exists() ?? false)
                    ->helperText(fn (?Poll $record) => $record?->votes()->exists() ? 'Options are locked because votes have been cast.' : 'One vote per verified stand. At least two options.')
                    ->columnSpanFull(),
                DateTimePicker::make('opens_at')->required()->seconds(false)->default(now()->startOfHour()),
                DateTimePicker::make('closes_at')->required()->seconds(false)->after('opens_at'),
                DateTimePicker::make('results_published_at')->label('Publish results at')->seconds(false)
                    ->helperText('Leave empty to keep results private to the committee.'),
            ]),
        ])->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('question')->columnSpanFull()->size('lg')->weight('bold'),
                TextEntry::make('description')->columnSpanFull()->placeholder('-'),
                TextEntry::make('state')->state(fn (Poll $record) => self::stateOf($record))->badge(),
                TextEntry::make('opens_at')->dateTime('j M Y, H:i'),
                TextEntry::make('closes_at')->dateTime('j M Y, H:i'),
                TextEntry::make('results_published_at')->label('Results public')->dateTime('j M Y, H:i')->placeholder('Private'),
            ]),
            Section::make('Results')->schema([
                ViewEntry::make('tally')->hiddenLabel()->view('filament.polls.tally'),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('opens_at', 'desc')
            ->columns([
                TextColumn::make('question')->searchable()->wrap()->limit(80),
                TextColumn::make('state')->state(fn (Poll $r) => self::stateOf($r))->badge()
                    ->color(fn ($state) => match ($state) {
                        'Open' => 'success', 'Scheduled' => 'info', default => 'gray'
                    }),
                TextColumn::make('votes_count')->label('Votes')->counts('votes'),
                TextColumn::make('opens_at')->dateTime('j M Y')->sortable(),
                TextColumn::make('closes_at')->dateTime('j M Y')->sortable(),
                TextColumn::make('results_published_at')->label('Results')->dateTime('j M Y')->placeholder('Private'),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPolls::route('/'),
            'create' => CreatePoll::route('/create'),
            'view' => ViewPoll::route('/{record}'),
            'edit' => EditPoll::route('/{record}/edit'),
        ];
    }
}
