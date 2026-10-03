<?php

namespace App\Filament\Resources\Notices;

use App\Filament\Resources\Notices\Pages\CreateNotice;
use App\Filament\Resources\Notices\Pages\EditNotice;
use App\Filament\Resources\Notices\Pages\ListNotices;
use App\Filament\Support\Uploads;
use App\Models\Notice;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class NoticeResource extends Resource
{
    protected static ?string $model = Notice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    public const SIGNATORIES = [
        'Chairperson' => 'Chairperson',
        'Vice Chairperson' => 'Vice Chairperson',
        'Treasurer' => 'Treasurer',
        'Secretary' => 'Secretary',
        'Technology Lead' => 'Technology Lead',
        'Legal and Deeds Liaison' => 'Legal and Deeds Liaison',
        'Community Lead' => 'Community Lead',
    ];

    public static function stateOf(Notice $n): string
    {
        if (! $n->published_at) {
            return 'Draft';
        }

        return $n->published_at->isFuture() ? 'Scheduled' : 'Published';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Notice')->columnSpan(2)->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation) {
                        if ($operation === 'create' || blank($get('slug'))) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText('Web address: /notices/{slug}'),
                Textarea::make('excerpt')->rows(2)->maxLength(300)->helperText('Shown in lists and SMS. Up to 300 characters.'),
                Uploads::richEditor('body')->required(),
            ]),
            Section::make('Publishing')->columnSpan(1)->schema([
                Select::make('category')->options(Notice::CATEGORIES)->required()->native(false),
                Select::make('signed_by_role')->label('Signed by')->options(self::SIGNATORIES)->native(false),
                DateTimePicker::make('published_at')
                    ->label('Publish at')
                    ->seconds(false)
                    ->helperText('Leave empty to keep as a draft. A future time schedules it.'),
                Toggle::make('pinned')->label('Pin to top'),
                Toggle::make('send_sms')->label('Send SMS to subscribers')
                    ->helperText(fn (?Notice $record) => $record?->notified_at ? 'Already notified '.$record->notified_at->diffForHumans().'.' : 'Sent once, when the notice is published.'),
            ]),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->limit(60)->wrap()
                    ->description(fn (Notice $r) => '/notices/'.$r->slug),
                TextColumn::make('category')->badge()->color(fn ($state) => $state === 'urgent' ? 'danger' : 'gray')
                    ->formatStateUsing(fn ($state) => Notice::CATEGORIES[$state] ?? $state),
                TextColumn::make('state')->label('State')->badge()
                    ->state(fn (Notice $r) => self::stateOf($r))
                    ->color(fn ($state) => match ($state) { 'Published' => 'success', 'Scheduled' => 'info', default => 'gray' }),
                IconColumn::make('pinned')->boolean()->trueIcon(Heroicon::OutlinedMapPin)->falseIcon(null),
                TextColumn::make('signed_by_role')->label('Signed by')->placeholder('-')->toggleable(),
                TextColumn::make('published_at')->dateTime('j M Y, H:i')->sortable()->placeholder('Draft'),
                IconColumn::make('send_sms')->label('SMS')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')->options(Notice::CATEGORIES),
                TernaryFilter::make('published')
                    ->label('Published')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('published_at')->where('published_at', '<=', now()),
                        false: fn (Builder $query) => $query->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '>', now())),
                    ),
                TernaryFilter::make('pinned'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->after(fn () => app(\App\Services\AssistantService::class)->rebuildKnowledge()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotices::route('/'),
            'create' => CreateNotice::route('/create'),
            'edit' => EditNotice::route('/{record}/edit'),
        ];
    }
}
