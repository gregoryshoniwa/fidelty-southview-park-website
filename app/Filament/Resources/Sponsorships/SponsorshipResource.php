<?php

namespace App\Filament\Resources\Sponsorships;

use App\Filament\Resources\Sponsorships\Pages\CreateSponsorship;
use App\Filament\Resources\Sponsorships\Pages\EditSponsorship;
use App\Filament\Resources\Sponsorships\Pages\ListSponsorships;
use App\Filament\Support\Uploads;
use App\Models\Sponsorship;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SponsorshipResource extends Resource
{
    protected static ?string $model = Sponsorship::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Adverts';

    protected static ?string $modelLabel = 'advert';

    protected static ?string $recordTitleAttribute = 'advertiser';

    protected static ?int $navigationSort = 6;

    public const STATUSES = ['active' => 'Active', 'paused' => 'Paused', 'ended' => 'Ended'];

    public static function slotOptions(): array
    {
        return collect(Sponsorship::SLOTS)->map(fn ($s) => $s['label'])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Advert')->columnSpan(2)->columns(2)->schema([
                TextInput::make('advertiser')->required()->maxLength(255),
                Select::make('slot')->options(self::slotOptions())->required()->native(false)->live()
                    ->helperText(fn (Get $get) => ($s = Sponsorship::SLOTS[$get('slot')]['size'] ?? null) ? 'Creative size: '.$s : null),
                TextInput::make('headline')->maxLength(255)->columnSpanFull(),
                Textarea::make('body')->rows(2)->maxLength(300)->columnSpanFull(),
                Uploads::image('creative_path', 'ads', 600)
                    ->label('Creative')
                    ->helperText('WebP, JPEG or PNG, up to 600 KB.')
                    ->columnSpanFull(),
                TextInput::make('click_url')->label('Click URL')->url()->maxLength(255),
                TextInput::make('cta_label')->label('Button label')->maxLength(40),
            ]),
            Section::make('Booking')->columnSpan(1)->schema([
                DatePicker::make('starts_on')->required()->native(false)->default(today()),
                DatePicker::make('ends_on')->required()->native(false)->afterOrEqual('starts_on'),
                TextInput::make('price')->numeric()->minValue(0)->default(0)->prefix('$'),
                Select::make('currency')->options(['USD' => 'USD', 'ZWG' => 'ZWG'])->default('USD')->required(),
                Select::make('status')->options(self::STATUSES)->default('active')->required(),
                Select::make('partner_id')->label('Partner (optional)')->options(fn () => \App\Models\Partner::orderBy('name')->pluck('name', 'id'))->searchable(),
            ]),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                ImageColumn::make('creative_path')->label('')->disk(Uploads::DISK)->height(40),
                TextColumn::make('advertiser')->searchable()->description(fn (Sponsorship $r) => $r->headline),
                TextColumn::make('slot')->badge()->color('gray')->formatStateUsing(fn ($s) => Sponsorship::SLOTS[$s]['label'] ?? $s),
                TextColumn::make('starts_on')->label('Runs')->date('j M')->sortable()
                    ->description(fn (Sponsorship $r) => 'to '.$r->ends_on?->format('j M Y')),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($s) => self::STATUSES[$s] ?? $s)
                    ->color(fn ($s) => match ($s) { 'active' => 'success', 'paused' => 'warning', default => 'gray' }),
                TextColumn::make('impressions')->numeric()->sortable(),
                TextColumn::make('clicks')->numeric()->sortable(),
                TextColumn::make('ctr')->label('CTR')
                    ->state(fn (Sponsorship $r) => $r->impressions > 0 ? round($r->clicks / $r->impressions * 100, 2) : null)
                    ->formatStateUsing(fn ($s) => $s === null ? '-' : number_format($s, 2).'%')
                    ->placeholder('-'),
                TextColumn::make('price')->money(fn (Sponsorship $r) => $r->currency)->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('slot')->options(self::slotOptions()),
                SelectFilter::make('status')->options(self::STATUSES),
                Filter::make('live')->label('Live today')
                    ->query(fn (Builder $query) => $query->where('status', 'active')->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSponsorships::route('/'),
            'create' => CreateSponsorship::route('/create'),
            'edit' => EditSponsorship::route('/{record}/edit'),
        ];
    }
}
