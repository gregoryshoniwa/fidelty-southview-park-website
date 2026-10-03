<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    protected $guarded = ['id'];

    public const MODULES = [
        'queue' => 'Request queue',
        'documents' => 'Document viewer',
        'status_updates' => 'Status updates to resident',
        'messages' => 'Two-way messages',
        'broadcast' => 'Broadcast to own clients',
        'settlements' => 'Settlement reports',
        'invoices' => 'Invoices and fee schedules',
        'incidents' => 'Incidents and alerts',
        'loans' => 'Loan applications',
        'export' => 'Export',
    ];

    public const TYPES = [
        'developer' => 'Property developer',
        'law_firm' => 'Law firm',
        'bank' => 'Bank',
        'school' => 'School',
        'security' => 'Security company',
        'business' => 'Business',
        'church' => 'Church',
    ];

    protected function casts(): array
    {
        return ['modules' => 'array', 'workflow_steps' => 'array', 'active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->modules ?? [], true);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }
}
