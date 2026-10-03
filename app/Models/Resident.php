<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resident extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['national_id_hash', 'fidelity_reference'];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'consent_fidelity_at' => 'datetime',
            'consent_docs_at' => 'datetime',
            'consent_marketing_at' => 'datetime',
            'consent_assistant_voice_at' => 'datetime',
            'fidelity_reference' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stand(): BelongsTo
    {
        return $this->belongsTo(Stand::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }
}
