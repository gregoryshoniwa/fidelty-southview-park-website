<?php

namespace App\Filament\Resources\Residents;

use App\Filament\Resources\Residents\Pages\ListResidents;
use App\Models\AuditLog;
use App\Models\Resident;
use App\Models\User;
use App\Services\NotificationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class ResidentResource extends Resource
{
    protected static ?string $model = Resident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 3;

    public const VERIFICATION = ['unverified' => 'Unverified', 'review' => 'Awaiting committee check', 'pending' => 'Code sent', 'verified' => 'Verified'];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function maskPhone(?string $phone): string
    {
        if (! $phone) {
            return '-';
        }

        return str_repeat('•', max(0, strlen($phone) - 3)).substr($phone, -3);
    }

    /** Residents whose stand or typed phone number the committee still has to check. */
    public static function needsCheckQuery(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('verification_status', 'review')
            ->orWhereHas('user', fn (Builder $u) => $u->whereNotNull('unconfirmed_phone')->whereNull('phone')));
    }

    public static function awaitingCommitteeCount(): int
    {
        return self::needsCheckQuery(Resident::query())->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $n = self::awaitingCommitteeCount();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Waiting for the committee to check a stand or phone number';
    }

    /** Move a typed (unconfirmed) number to the confirmed phone, unless another account already holds it. */
    public static function confirmPhoneFor(?User $user): bool
    {
        $phone = $user?->unconfirmed_phone;
        if (! $phone || $user->phone) {
            return true;
        }
        if (User::where('phone', $phone)->where('id', '!=', $user->id)->exists()) {
            return false;
        }
        $user->forceFill(['phone' => $phone, 'phone_verified_at' => now(), 'unconfirmed_phone' => null])->save();
        AuditLog::record('user.phone_confirmed_by_committee', $user);

        return true;
    }

    public static function table(Table $table): Table
    {
        return $table
            // Select only what the list needs: never national_id_hash or fidelity_reference.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->select(['residents.id', 'residents.user_id', 'residents.stand_id', 'residents.verification_status', 'residents.verified_at', 'residents.created_at'])
                ->with(['user:id,name,phone,unconfirmed_phone,status', 'stand:id,stand_number']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Name')->searchable(),
                TextColumn::make('phone')->label('Phone')->fontFamily('mono')
                    ->state(fn (Resident $r) => $r->user?->phone ? self::maskPhone($r->user->phone) : ($r->user?->unconfirmed_phone ? self::maskPhone($r->user->unconfirmed_phone).' (unconfirmed)' : '-')),
                TextColumn::make('stand.stand_number')->label('Stand')->searchable()->placeholder('-'),
                TextColumn::make('verification_status')->label('Verification')->badge()
                    ->formatStateUsing(fn ($state) => self::VERIFICATION[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'verified' => 'success', 'pending', 'review' => 'warning', default => 'gray'
                    }),
                TextColumn::make('verified_at')->dateTime('j M Y')->sortable()->placeholder('-'),
                TextColumn::make('user.status')->label('Account')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('created_at')->label('Registered')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('needs_check')->label('Waiting for the committee')->toggle()
                    ->query(fn (Builder $query) => self::needsCheckQuery($query)),
                SelectFilter::make('verification_status')->label('Verification')->options(self::VERIFICATION),
                SelectFilter::make('account')->label('Account')
                    ->options(['active' => 'Active', 'suspended' => 'Suspended'])
                    ->query(fn (Builder $query, array $data) => filled($data['value']) ? $query->whereHas('user', fn ($u) => $u->where('status', $data['value'])) : $query),
            ])
            ->recordActions([
                Action::make('confirmPhone')
                    ->label('Confirm phone')
                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                    ->color('warning')
                    ->visible(fn (Resident $record) => $record->user?->unconfirmed_phone && ! $record->user->phone)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Resident $record) => 'Confirm only after you have checked that '.$record->user->unconfirmed_phone.' belongs to '.$record->user->name.', for example by calling it or matching Fidelity Life records.')
                    ->action(function (Resident $record) {
                        if (! self::confirmPhoneFor($record->user)) {
                            Notification::make()->title('This number is already linked to another account')->danger()->send();

                            return;
                        }
                        Notification::make()->title('Phone number confirmed')->success()->send();
                    }),
                Action::make('confirmStand')
                    ->label('Confirm stand')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (Resident $record) => $record->verification_status === 'review')
                    ->requiresConfirmation()
                    ->modalDescription('Confirm only after checking Fidelity Life records show this person owns this stand.')
                    ->action(function (Resident $record) {
                        $taken = Resident::where('stand_id', $record->stand_id)->where('verification_status', 'verified')->where('id', '!=', $record->id)->exists();
                        if ($taken) {
                            Notification::make()->title('This stand is already verified to another resident')->danger()->send();

                            return;
                        }
                        $record->update(['verification_status' => 'verified', 'verified_at' => now()]);
                        self::confirmPhoneFor($record->user); // the committee has checked Fidelity's records, which hold the owner's number
                        $record->user->assignRole('verified_resident');
                        AuditLog::record('verification.confirmed_by_committee', $record);
                        app(NotificationService::class)->notify($record->user, 'Your stand is verified', 'Stand '.$record->stand?->stand_number.' is linked to your account. Every service is now open to you.', '/app', 'verification');
                        Notification::make()->title('Stand confirmed and resident notified')->success()->send();
                    }),
                Action::make('rejectStand')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Resident $record) => $record->verification_status === 'review')
                    ->requiresConfirmation()
                    ->action(function (Resident $record) {
                        $record->update(['verification_status' => 'unverified', 'stand_id' => null]);
                        AuditLog::record('verification.rejected_by_committee', $record);
                        app(NotificationService::class)->notify($record->user, 'We could not confirm your stand', 'Fidelity Life records did not match. Write to the committee from the app and we will help.', '/app/inbox/new', 'verification');
                        Notification::make()->title('Rejected and resident notified')->success()->send();
                    }),
                ActionGroup::make([
                    Action::make('suspend')
                        ->label('Suspend user')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('warning')
                        ->visible(fn (Resident $record) => $record->user?->status === 'active')
                        ->requiresConfirmation()
                        ->modalDescription('The resident is signed out of the app and cannot sign in until reactivated.')
                        ->action(function (Resident $record) {
                            $record->user->update(['status' => 'suspended']);
                            $record->user->tokens()->delete();
                            AuditLog::record('resident.suspended', $record);
                            Notification::make()->title('User suspended')->success()->send();
                        }),
                    Action::make('reactivate')
                        ->label('Reactivate user')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->visible(fn (Resident $record) => $record->user && $record->user->status !== 'active' && $record->user->name !== 'Deleted resident')
                        ->requiresConfirmation()
                        ->action(function (Resident $record) {
                            $record->user->update(['status' => 'active']);
                            AuditLog::record('resident.reactivated', $record);
                        }),
                    Action::make('deletePersonalData')
                        ->label('Delete personal data')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->visible(fn (Resident $record) => $record->user?->name !== 'Deleted resident')
                        ->modalHeading('Delete personal data')
                        ->modalDescription('This anonymises the account, removes the ID link and Fidelity reference, and permanently deletes all uploaded documents. It cannot be undone. Request, payment and ledger records are kept without personal details.')
                        ->modalSubmitActionLabel('Delete personal data')
                        ->schema([
                            TextInput::make('confirm')->label('Type DELETE to confirm')->required()->in(['DELETE'])
                                ->validationMessages(['in' => 'Type DELETE in capitals to confirm.']),
                        ])
                        ->action(function (Resident $record) {
                            self::deletePersonalData($record);
                            Notification::make()->title('Personal data deleted')->success()->send();
                        }),
                ]),
            ]);
    }

    public static function deletePersonalData(Resident $record): void
    {
        $resident = Resident::withTrashed()->findOrFail($record->id); // full row, including hidden columns
        $user = $resident->user;
        $docCount = 0;

        DB::transaction(function () use ($resident, $user, &$docCount) {
            if ($user) {
                $user->update([
                    'name' => 'Deleted resident',
                    'phone' => '+000'.$user->id,
                    'email' => null,
                    'status' => 'suspended',
                ]);
                $user->tokens()->delete();
            }

            $resident->update([
                'national_id_hash' => null,
                'national_id_last4' => null,
                'fidelity_reference' => null,
                'phone_on_file_masked' => null,
            ]);

            foreach ($resident->documents()->get() as $doc) {
                if ($doc->storage_path) {
                    Storage::disk('local')->delete($doc->storage_path);
                }
                $doc->delete();
                $docCount++;
            }
        });

        AuditLog::record('resident.data_deleted', $resident, ['documents_deleted' => $docCount]);
    }

    public static function getPages(): array
    {
        return ['index' => ListResidents::route('/')];
    }
}
