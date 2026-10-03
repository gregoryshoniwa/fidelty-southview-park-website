<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notice extends Model
{
    protected $guarded = ['id'];

    public const CATEGORIES = [
        'urgent' => 'Urgent',
        'services' => 'Services',
        'deeds' => 'Deeds',
        'finance' => 'Finance',
        'events' => 'Events',
        'security' => 'Security',
        'poll' => 'Poll',
        'sponsored' => 'Sponsored',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'notified_at' => 'datetime', 'pinned' => 'boolean', 'send_sms' => 'boolean'];
    }

    public function scopePublished($q)
    {
        return $q->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
