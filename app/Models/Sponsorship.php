<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sponsorship extends Model
{
    protected $guarded = ['id'];

    public const SLOTS = [
        'hero_takeover' => ['label' => 'Hero takeover (Presented by)', 'size' => '1280x720 video or still'],
        'billboard' => ['label' => 'Billboard', 'size' => '970x250 (320x100 mobile)'],
        'medium_rect' => ['label' => 'Medium rectangle', 'size' => '300x250'],
        'half_page' => ['label' => 'Half page', 'size' => '300x600'],
        'sponsored_tile' => ['label' => 'Sponsored directory tile', 'size' => '4:3 photo'],
        'sponsored_notice' => ['label' => 'Sponsored notice', 'size' => 'Text and link'],
        'logo_strip' => ['label' => 'Community partner logo strip', 'size' => 'Logo'],
    ];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'price' => 'decimal:2'];
    }

    public function scopeLive($q, string $slot)
    {
        return $q->where('slot', $slot)->where('status', 'active')
            ->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today());
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
