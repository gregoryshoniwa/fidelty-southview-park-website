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
