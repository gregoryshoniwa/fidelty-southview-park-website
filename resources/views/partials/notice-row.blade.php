@php $sponsored = $n->category === 'sponsored'; @endphp
<article @class(['flex gap-4 py-5', '-mx-5 bg-cream px-5 sm:-mx-6 sm:px-6' => $sponsored])>
    <time datetime="{{ $n->published_at->toDateString() }}" class="flex h-fit min-w-[52px] flex-col items-center rounded-[8px] px-2 py-2 leading-tight {{ $sponsored ? 'border border-line bg-white text-muted' : 'bg-forest-100 text-forest-700' }}">
        <span class="font-serif text-xl font-bold">{{ $n->published_at->format('d') }}</span>
        <span class="text-[10px] font-extrabold uppercase tracking-wider">{{ $n->published_at->format('M') }}</span>
    </time>
    <div class="flex min-w-0 flex-col gap-1.5">
        @if ($sponsored)
            <span class="sponsored-label">From the association</span>
        @else
            <span class="chip {{ in_array($n->category, ['urgent', 'security']) ? 'chip-red' : ($n->category === 'deeds' ? 'chip-gold' : 'chip-green') }} self-start">{{ \App\Models\Notice::CATEGORIES[$n->category] ?? $n->category }}</span>
        @endif
        <h3 class="font-sans text-base font-extrabold leading-snug text-forest-900"><a href="{{ route('notice', $n) }}" class="hover:underline">{{ $n->title }}</a></h3>
        @if ($n->excerpt)<p class="text-sm leading-relaxed text-muted">{{ $n->excerpt }}</p>@endif
        @if ($n->signed_by_role)<p class="text-xs font-semibold text-muted">Signed: {{ $n->signed_by_role }}</p>@endif
    </div>
</article>
