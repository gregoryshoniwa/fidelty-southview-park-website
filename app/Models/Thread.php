<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends Model
{
    protected $guarded = ['id'];

    public const CATEGORIES = [
        'general' => 'General',
        'deeds' => 'Title deeds',
        'payments' => 'Payments',
        'security' => 'Security',
        'water' => 'Water and roads',
        'complaint' => 'Complaint',
        'suggestion' => 'Suggestion',
        'assistant' => 'From the assistant',
    ];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'first_response_at' => 'datetime'];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function isCommitteeInbox(): bool
    {
        return $this->partner_id === null;
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
