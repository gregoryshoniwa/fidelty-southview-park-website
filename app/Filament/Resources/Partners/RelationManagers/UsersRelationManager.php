<?php

namespace App\Filament\Resources\Partners\RelationManagers;

use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Partner users';

    protected static ?string $recordTitleAttribute = 'name';

    public const ROLES = ['admin' => 'Admin', 'agent' => 'Agent'];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('role')->options(self::ROLES)->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('email')->placeholder('-'),
                TextColumn::make('role')->label('Partner role')->badge()->formatStateUsing(fn ($s) => self::ROLES[$s] ?? $s),
                TextColumn::make('status')->badge()->color(fn ($s) => $s === 'active' ? 'success' : 'danger'),
                TextColumn::make('last_login_at')->label('Last login')->since()->placeholder('Never'),
            ])
            ->headerActions([
                Action::make('createPartnerUser')
                    ->label('Create partner user')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->modalHeading('Create a partner user')
                    ->modalDescription('The user signs in to the partner portal with this work email and password, then a code sent to that email.')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('email')->label('Work email')->email()->required()->maxLength(255)->unique(User::class, 'email'),
                        TextInput::make('phone')->label('Phone (E.164, for contact)')->tel()->placeholder('+2637XXXXXXXX')
                            ->regex('/^\+263[1-9]\d{7,9}$/')
                            ->validationMessages(['regex' => 'Use the international format, e.g. +263771234567.'])
                            ->unique(User::class, 'phone'),
                        TextInput::make('password')->password()->revealable()->required()->minLength(12),
                        Select::make('role')->label('Partner role')->options(self::ROLES)->default('agent')->required(),
                    ])
                    ->action(function (array $data) {
                        /** @var Partner $partner */
                        $partner = $this->getOwnerRecord();
                        $user = DB::transaction(function () use ($data, $partner) {
                            $user = User::create([
                                'name' => $data['name'],
                                'phone' => $data['phone'] ?: null,
                                'email' => strtolower($data['email']),
                                'email_verified_at' => now(),
                                'password' => $data['password'],
                                'phone_verified_at' => $data['phone'] ? now() : null,
                                'status' => 'active',
                            ]);
                            $user->assignRole('partner_user');
                            $partner->users()->attach($user->id, ['role' => $data['role']]);

                            return $user;
                        });
                        AuditLog::record('admin.partner.user_created', $partner, ['user_id' => $user->id, 'role' => $data['role']]);
                        Notification::make()->title('Partner user created')->success()->send();
                    }),
                AttachAction::make()
                    ->label('Attach existing user')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'phone', 'email'])
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', User::COMMITTEE_ROLES)))
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('role')->label('Partner role')->options(self::ROLES)->default('agent')->required(),
                    ])
                    ->after(function (AttachAction $action) {
                        $user = $action->getRecord();
                        if ($user instanceof User && ! $user->hasRole('partner_user')) {
                            $user->assignRole('partner_user');
                        }
                        AuditLog::record('admin.partner.user_attached', $this->getOwnerRecord(), ['user_id' => $user?->id]);
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('Change role'),
                DetachAction::make()->after(fn (User $record) => AuditLog::record('admin.partner.user_detached', $this->getOwnerRecord(), ['user_id' => $record->id])),
            ]);
    }
}
