<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    protected $hidden = ['gateway_payload'];

    public const BILLERS = [
        'council' => ['label' => 'City of Harare rates', 'reference' => 'Council account number', 'fee' => 0.50, 'commission_percent' => 0],
        'zesa' => ['label' => 'ZESA prepaid token', 'reference' => 'Meter number', 'fee' => 0, 'commission_percent' => 1.5],
        'airtime' => ['label' => 'Airtime or data', 'reference' => 'Phone number', 'fee' => 0, 'commission_percent' => 4],
        'legal' => ['label' => 'Law firm deed fees', 'reference' => 'Deed file reference', 'fee' => 1.00, 'commission_percent' => 0],
        'school' => ['label' => 'Partner school fees', 'reference' => 'Learner number', 'fee' => 0, 'commission_percent' => 1.5],
        'dstv' => ['label' => 'DStv subscription', 'reference' => 'Smartcard number', 'fee' => 0, 'commission_percent' => 2],
        'merchant' => ['label' => 'Other merchant', 'reference' => 'Merchant code', 'fee' => 0, 'commission_percent' => 0],
        'agreement' => ['label' => 'Replacement Agreement of Sale', 'reference' => 'Request reference', 'fee' => 0, 'commission_percent' => 0],
        'order' => ['label' => 'Community shop order', 'reference' => 'Order reference', 'fee' => 0, 'commission_percent' => 3],
        'sponsorship' => ['label' => 'Advertising', 'reference' => 'Booking reference', 'fee' => 0, 'commission_percent' => 100],
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'commission' => 'decimal:2',
            'total' => 'decimal:2',
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
