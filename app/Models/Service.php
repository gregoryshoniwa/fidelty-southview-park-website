<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'requires_verification' => 'boolean',
            'fee_amount' => 'decimal:2',
            'commission_percent' => 'decimal:3',
            'form_schema' => 'array',
            'steps' => 'array',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** Who provides the service, as shown to the public. Deeds are handled by several law firms. */
    public function providerName(string $fallback = 'The association'): string
    {
        return match (true) {
            $this->slug === 'title-deed-tracker' => 'Five law firms',
            default => $this->partner?->name ?? $fallback,
        };
    }

    /** Partners shown with the service: every active law firm for deeds, otherwise the one partner. */
    public function providerPartners(): \Illuminate\Support\Collection
    {
        return $this->slug === 'title-deed-tracker'
            ? Partner::where('type', 'law_firm')->where('active', true)->whereNotNull('logo_path')->orderBy('name')->get()
            : collect($this->partner ? [$this->partner] : []);
    }

    public function feeLabel(): string
    {
        return match ($this->fee_type) {
            'flat' => $this->fee_currency.' '.number_format((float) $this->fee_amount, 2),
            'percent' => rtrim(rtrim(number_format((float) $this->fee_amount, 2), '0'), '.').' percent',
            'partner_paid' => 'Free to you (partner pays)',
            default => 'Free',
        };
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
