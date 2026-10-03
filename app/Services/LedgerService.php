<?php

namespace App\Services;

use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /** Append an entry with a SHA-256 hash chain over the previous entry. */
    public function post(array $attrs): LedgerEntry
    {
        return DB::transaction(function () use ($attrs) {
            $prev = LedgerEntry::lockForUpdate()->orderByDesc('id')->value('hash_self');
            $attrs['entry_date'] ??= today()->toDateString();
            $attrs['amount'] = number_format((float) $attrs['amount'], 2, '.', '');
            $attrs['status'] ??= 'posted';
            $payload = json_encode([
                $prev, $attrs['entry_date'], $attrs['type'], $attrs['description'], (string) $attrs['amount'],
                $attrs['currency'] ?? 'USD', $attrs['payment_id'] ?? null, $attrs['source'],
            ]);
            $attrs['hash_prev'] = $prev;
            $attrs['hash_self'] = hash('sha256', $payload);

            return LedgerEntry::create($attrs);
        });
    }

    public function reverse(LedgerEntry $entry, string $reason, int $userId): LedgerEntry
    {
        return $this->post([
            'type' => 'adjustment',
            'description' => 'Reversal of #'.$entry->id.': '.$reason,
            'amount' => -1 * (float) $entry->amount,
            'currency' => $entry->currency,
            'source' => 'treasurer',
            'created_by' => $userId,
            'reverses_id' => $entry->id,
            'service_id' => $entry->service_id,
            'partner_id' => $entry->partner_id,
        ]);
    }

    /** @return array{ok: bool, broken_at: ?int} */
    public function verifyChain(): array
    {
        $prev = null;
        foreach (LedgerEntry::orderBy('id')->cursor() as $e) {
            $payload = json_encode([$prev, $e->entry_date->toDateString(), $e->type, $e->description, number_format((float) $e->amount, 2, '.', ''), $e->currency, $e->payment_id, $e->source]);
            if ($e->hash_prev !== $prev || ! hash_equals($e->hash_self, hash('sha256', $payload))) {
                return ['ok' => false, 'broken_at' => $e->id];
            }
            $prev = $e->hash_self;
        }

        return ['ok' => true, 'broken_at' => null];
    }

    public function balance(string $currency = 'USD'): float
    {
        return (float) LedgerEntry::where('currency', $currency)->where('status', 'posted')->sum('amount');
    }
}
