@php
    $nav = [['Services', route('services'), 'services*'], ['Community', route('community'), 'community*'], ['Notices', route('notices'), 'notices*'], ['About us', route('about'), 'about*']];
@endphp
<header class="sticky top-0 z-40 border-b border-white/10 bg-forest-900/95 text-cream backdrop-blur supports-[backdrop-filter]:bg-forest-900/85" x-data="nav">
    <div class="wrap flex h-[72px] items-center justify-between gap-4 lg:h-[88px]">
        <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="Southview Park Residents Association home">
            <img src="/images/logo-128.webp" width="64" height="64" alt="" class="size-14 rounded-[10px] bg-white object-cover shadow-sm ring-1 ring-gold-500/40 lg:size-16">
            <span class="flex flex-col leading-tight">
                <span class="font-serif text-base font-bold whitespace-nowrap sm:text-lg lg:text-xl"><span class="hidden sm:inline">Fidelity </span>Southview Park</span>
                <span class="text-[9px] font-extrabold uppercase tracking-[0.14em] text-gold-500 whitespace-nowrap sm:text-[10px] sm:tracking-[0.16em]">Residents Association</span>
            </span>
        </a>
        <nav class="hidden items-center gap-7 lg:flex" aria-label="Main">
            @foreach ($nav as [$label, $href, $pattern])
                <a href="{{ $href }}" @class(['text-[15px] font-semibold transition hover:text-gold-400', 'text-gold-400' => request()->is($pattern), 'text-cream/90' => !request()->is($pattern)]) @if(request()->is($pattern)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="flex items-center gap-2">
            <a href="/app/login" class="btn btn-ghost btn-sm hidden sm:inline-flex">Sign in</a>
            <a href="/app/verify" class="btn btn-gold btn-sm whitespace-nowrap"><span class="sm:hidden">Verify</span><span class="hidden sm:inline">Verify my stand</span></a>
            <button type="button" class="ml-1 inline-flex size-10 items-center justify-center rounded-[6px] text-cream hover:bg-white/10 lg:hidden" x-on:click="toggle" x-bind:aria-expanded="openStr" aria-controls="mobile-nav" aria-label="Menu">
                <span x-show="closed"><x-lucide name="menu" class="size-6" /></span>
                <span x-show="open" x-cloak><x-lucide name="x" class="size-6" /></span>
            </button>
        </div>
    </div>
    <div id="mobile-nav" class="border-t border-white/10 lg:hidden" x-show="open" x-cloak x-transition.opacity>
        <nav class="wrap flex flex-col py-3" aria-label="Mobile">
            @foreach ($nav as [$label, $href, $pattern])
                <a href="{{ $href }}" class="flex min-h-12 items-center justify-between border-b border-white/10 text-base font-semibold">{{ $label }} <x-lucide name="chevron-right" class="size-4 text-gold-500" /></a>
            @endforeach
            <a href="/app/login" class="btn btn-ghost mt-4">Sign in</a>
        </nav>
    </div>
</header>
