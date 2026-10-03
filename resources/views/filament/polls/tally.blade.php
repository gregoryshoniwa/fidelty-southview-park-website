@php
    $rows = $getRecord()->tally();
    $total = array_sum(array_column($rows, 'votes'));
    $max = max(1, ...array_column($rows, 'votes') ?: [0]);
@endphp

<div style="display:flex;flex-direction:column;gap:0.75rem;">
    @foreach ($rows as $row)
        @php $pct = $total ? round($row['votes'] / $total * 100, 1) : 0; @endphp
        <div>
            <div style="display:flex;justify-content:space-between;gap:1rem;font-size:0.9rem;margin-bottom:0.25rem;">
                <span>{{ $row['label'] }}</span>
                <span style="font-variant-numeric:tabular-nums;"><strong>{{ number_format($row['votes']) }}</strong> ({{ $pct }}%)</span>
            </div>
            <div style="height:0.6rem;border-radius:999px;background:rgba(120,113,108,0.15);overflow:hidden;" role="presentation">
                <div style="height:100%;width:{{ $pct }}%;background:{{ $row['votes'] === $max && $row['votes'] > 0 ? '#0E4D2E' : 'rgba(14,77,46,0.55)' }};"></div>
            </div>
        </div>
    @endforeach
    <p style="font-size:0.85rem;opacity:0.7;margin-top:0.25rem;">{{ number_format($total) }} {{ \Illuminate\Support\Str::plural('vote', $total) }} cast, one per verified stand.</p>
</div>
