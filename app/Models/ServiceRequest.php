<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceRequest extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = [
        'open' => 'Open',
        'waiting_resident' => 'Waiting on you',
        'waiting_partner' => 'With partner',
        'waiting_payment' => 'Waiting for payment',
        'approved' => 'Approved',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ];

    protected function casts(): array
    {
        return ['data' => 'array', 'closed_at' => 'datetime'];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RequestEvent::class)->orderBy('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function thread(): HasOne
    {
        return $this->hasOne(Thread::class);
    }

    public function stepsList(): array
    {
        return $this->partner?->workflow_steps ?: ($this->service?->steps ?: []);
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
