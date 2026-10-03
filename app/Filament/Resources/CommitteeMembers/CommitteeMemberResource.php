<?php

namespace App\Filament\Resources\CommitteeMembers;

use App\Filament\Resources\CommitteeMembers\Pages\ManageCommitteeMembers;
use App\Filament\Support\Uploads;
use App\Models\CommitteeMember;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class CommitteeMemberResource extends Resource
{
    protected static ?string $model = CommitteeMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Committee members';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('role')->required()->maxLength(255)->datalist(array_keys(\App\Filament\Resources\Notices\NoticeResource::SIGNATORIES)),
            TextInput::make('area')->maxLength(255)->helperText('Portfolio or area of responsibility.'),
            TextInput::make('sort')->numeric()->minValue(0)->default(0),
            Uploads::image('photo_path', 'committee', 1024)
                ->label('Photo')
                ->avatar()
                ->imageEditor()
                ->imageAspectRatio('1:1')
                ->automaticallyCropImagesToAspectRatio()
                ->automaticallyResizeImagesToWidth('400')
                ->automaticallyResizeImagesToHeight('400')
                ->columnSpanFull(),
            Textarea::make('bio')->rows(4)->maxLength(2000)->columnSpanFull(),
            Toggle::make('public')->label('Show on the public About page'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                ImageColumn::make('photo_path')->label('')->disk(Uploads::DISK)->circular()->defaultImageUrl('/images/avatar.svg'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('role')->searchable(),
                TextColumn::make('area')->placeholder('-'),
                IconColumn::make('public')->boolean(),
            ])
            ->filters([TernaryFilter::make('public')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCommitteeMembers::route('/')];
    }
}
