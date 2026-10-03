<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit log is append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit log is append-only.'));
    }

    public static function record(string $action, ?Model $subject = null, array $meta = [], ?User $actor = null): self
    {
        $actor ??= auth()->user();
        $req = request();

        return static::create([
            'actor_type' => $actor ? 'user' : 'system',
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => $req?->ip(),
            'user_agent' => substr((string) $req?->userAgent(), 0, 255),
        ]);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
