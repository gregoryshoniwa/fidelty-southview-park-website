<?php

namespace App\Filament\Resources\Minutes;

use App\Filament\Resources\Minutes\Pages\CreateMinute;
use App\Filament\Resources\Minutes\Pages\EditMinute;
use App\Filament\Resources\Minutes\Pages\ListMinutes;
use App\Filament\Support\Uploads;
use App\Models\Minute;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class MinuteResource extends Resource
{
    protected static ?string $model = Minute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                DatePicker::make('meeting_date')->required()->native(false),
                DateTimePicker::make('published_at')->label('Publish at')->seconds(false)->helperText('Leave empty to keep as a draft.'),
                Uploads::richEditor('body')->required(),
                Uploads::public('file_path', 'minutes')
                    ->label('Signed minutes (PDF)')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240)
                    ->downloadable()
                    ->columnSpanFull(),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('meeting_date', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('meeting_date')->date('j M Y')->sortable(),
                IconColumn::make('file_path')->label('PDF')->boolean()->state(fn (Minute $r) => filled($r->file_path)),
                TextColumn::make('published_at')->dateTime('j M Y, H:i')->placeholder('Draft')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('published_at')->label('Published')->nullable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMinutes::route('/'),
            'create' => CreateMinute::route('/create'),
            'edit' => EditMinute::route('/{record}/edit'),
        ];
    }
}
