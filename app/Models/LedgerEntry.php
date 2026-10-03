<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    protected $guarded = ['id'];

    public const TYPES = [
        'commission' => 'Commission',
        'fee' => 'Platform fee',
        'advertising' => 'Advertising',
        'expense' => 'Expense',
        'payout' => 'Payout',
        'adjustment' => 'Adjustment (reversal)',
    ];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'amount' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (LedgerEntry $e) {
            $allowed = ['status', 'approved_by', 'approved_at', 'updated_at'];
            if (array_diff(array_keys($e->getDirty()), $allowed)) {
                throw new \LogicException('Ledger entries are append-only. Post a reversing entry instead.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Ledger entries cannot be deleted.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
