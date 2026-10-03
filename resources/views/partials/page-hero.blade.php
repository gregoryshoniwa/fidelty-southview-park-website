<section class="bg-forest-900 text-cream">
    <div class="wrap py-14 lg:py-16">
        @isset($crumbs)
            <nav aria-label="Breadcrumb" class="mb-4 text-sm text-cream/70">
                <ol class="flex flex-wrap items-center gap-1.5">
                    <li><a href="{{ route('home') }}" class="hover:text-gold-400">Home</a></li>
                    @foreach ($crumbs as [$label, $href])
                        <li class="flex items-center gap-1.5"><x-lucide name="chevron-right" class="size-3.5" />@if($href)<a href="{{ $href }}" class="hover:text-gold-400">{{ $label }}</a>@else<span aria-current="page" class="text-cream">{{ $label }}</span>@endif</li>
                    @endforeach
                </ol>
            </nav>
        @endisset
        @isset($eyebrow)<p class="eyebrow mb-3 text-gold-500">{{ $eyebrow }}</p>@endisset
        <h1 class="max-w-3xl font-serif text-4xl font-bold leading-tight sm:text-5xl">{{ $heading }}</h1>
        @isset($lead)<p class="mt-4 max-w-2xl text-lg leading-relaxed text-cream/80">{{ $lead }}</p>@endisset
    </div>
</section>
