<?php

namespace App\Models;

use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasName
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const COMMITTEE_ROLES = ['committee', 'finance_admin', 'super_admin'];

    protected $fillable = ['name', 'phone', 'email', 'email_verified_at', 'password', 'locale', 'status', 'notification_prefs', 'last_login_at', 'phone_verified_at'];

    protected $attributes = ['status' => 'active', 'locale' => 'en'];

    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'notification_prefs' => 'array',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function resident(): HasOne
    {
        return $this->hasOne(Resident::class);
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class)->withPivot('role')->withTimestamps();
    }

    public function isCommittee(): bool
    {
        return $this->hasAnyRole(self::COMMITTEE_ROLES);
    }

    public function isFinanceAdmin(): bool
    {
        return $this->hasAnyRole(['finance_admin', 'super_admin']);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === 'active' && $this->isCommittee() && $this->password !== null;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return asset('images/avatar.svg');
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->phone ?? $this->email ?? (string) $this->id;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
