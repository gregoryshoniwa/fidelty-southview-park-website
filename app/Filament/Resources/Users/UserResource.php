<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Committee users';

    protected static ?string $modelLabel = 'committee user';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public const ROLE_LABELS = [
        'committee' => 'Committee (content, inbox, community)',
        'finance_admin' => 'Finance admin (ledger, payments, payouts)',
        'super_admin' => 'Super admin (committee users)',
    ];

    public static function isSuperAdmin(): bool
    {
        $u = auth()->user();

        return $u instanceof User && $u->hasRole('super_admin');
    }

    public static function canViewAny(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return self::isSuperAdmin();
    }

    /** Suspend instead of deleting: users own audit, ledger and message history. */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('roles', fn ($query) => $query->whereIn('name', User::COMMITTEE_ROLES));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->label('Email (used to sign in)')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('phone')->label('Phone (E.164)')->required()->tel()->placeholder('+2637XXXXXXXX')
                    ->regex('/^\+[1-9]\d{7,14}$/')
                    ->validationMessages(['regex' => 'Use the international format, e.g. +263771234567.'])
                    ->unique(ignoreRecord: true),
                Select::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended'])->default('active')->required()
                    ->disabled(fn (?User $record) => $record?->is(auth()->user()))
                    ->dehydrated(fn (?User $record) => ! $record?->is(auth()->user())),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(12)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the current password. At least 12 characters.' : 'At least 12 characters.')
                    ->columnSpanFull(),
            ]),
            Section::make('Roles')->schema([
                CheckboxList::make('committee_roles')
                    ->hiddenLabel()
                    ->options(self::ROLE_LABELS)
                    ->required()
                    ->dehydrated(false)
                    ->helperText('Finance admins see the ledger and payments. Only super admins manage committee users.'),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('roles.name')->label('Roles')->badge()
                    ->state(fn (User $r) => $r->roles->pluck('name')->intersect(User::COMMITTEE_ROLES)->values()->all()),
                IconColumn::make('mfa')->label('2FA')->boolean()->state(fn (User $r) => filled($r->app_authentication_secret)),
                TextColumn::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('last_login_at')->label('Last login')->since()->placeholder('Never'),
            ])
            ->filters([
                SelectFilter::make('role')->options(array_combine(User::COMMITTEE_ROLES, User::COMMITTEE_ROLES))
                    ->query(fn (Builder $query, array $data) => filled($data['value']) ? $query->whereHas('roles', fn ($r) => $r->where('name', $data['value'])) : $query),
                SelectFilter::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended']),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
