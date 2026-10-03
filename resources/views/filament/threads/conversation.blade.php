@php
    /** @var \App\Models\Thread $thread */
    $thread = $getRecord();
    $messages = $thread->messages()->get();
    $staffNames = \App\Models\User::whereIn('id', $messages->whereIn('sender_type', ['committee', 'partner_user'])->pluck('sender_id')->filter()->unique())->pluck('name', 'id');
    $label = fn ($m) => match ($m->sender_type) {
        'resident' => ($thread->resident?->user?->name ?? 'Resident').' (resident)',
        'committee' => 'Committee'.(isset($staffNames[$m->sender_id]) ? ' ('.$staffNames[$m->sender_id].')' : ''),
        'partner_user' => ($thread->partner?->name ?? 'Partner').(isset($staffNames[$m->sender_id]) ? ' ('.$staffNames[$m->sender_id].')' : ''),
        'assistant' => 'Assistant',
        default => 'System',
    };
@endphp

<div style="display:flex;flex-direction:column;gap:0.75rem;">
    @forelse ($messages as $m)
        @php $mine = in_array($m->sender_type, ['committee', 'partner_user'], true); @endphp
        <div style="display:flex;justify-content:{{ $mine ? 'flex-end' : 'flex-start' }};">
            <div style="max-width:42rem;width:100%;border-radius:0.75rem;padding:0.75rem 1rem;border:1px solid rgba(120,113,108,0.25);{{ $mine ? 'background:rgba(14,77,46,0.08);' : 'background:rgba(120,113,108,0.06);' }}">
                <div style="display:flex;justify-content:space-between;gap:1rem;font-size:0.8rem;opacity:0.75;margin-bottom:0.35rem;">
                    <strong>{{ $label($m) }}</strong>
                    <span title="{{ $m->created_at?->toDayDateTimeString() }}">{{ $m->created_at?->format('j M Y, H:i') }}</span>
                </div>
                <div style="white-space:pre-wrap;word-break:break-word;font-size:0.925rem;line-height:1.5;">{{ $m->body }}</div>
                @if (! empty($m->attachments))
                    <div style="margin-top:0.5rem;font-size:0.8rem;opacity:0.75;">
                        Attachments: {{ collect($m->attachments)->map(fn ($a) => is_array($a) ? ($a['name'] ?? $a['original_name'] ?? 'file') : basename((string) $a))->implode(', ') }}
                    </div>
                @endif
            </div>
        </div>
    @empty
        <p style="opacity:0.7;">No messages yet.</p>
    @endforelse
</div>
