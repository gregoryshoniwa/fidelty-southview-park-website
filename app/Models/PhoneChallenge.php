<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneChallenge extends Model
{
    use HasUlids;

    protected $fillable = ['code', 'purpose', 'user_id', 'status', 'phone', 'expires_at', 'verified_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLive(): bool
    {
        return $this->expires_at->isFuture();
    }
}
