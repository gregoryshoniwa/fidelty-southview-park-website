@props(['eyebrow' => null, 'title', 'lead' => null, 'dark' => false, 'h' => 'h2'])
<div {{ $attributes->merge(['class' => 'flex max-w-2xl flex-col gap-3']) }}>
    @if ($eyebrow)<span @class(['eyebrow', 'text-gold-600' => !$dark, 'text-gold-500' => $dark])>{{ $eyebrow }}</span>@endif
    <{{ $h }} @class(['font-serif text-3xl font-bold leading-tight sm:text-4xl lg:text-[42px]', 'text-forest-900' => !$dark, 'text-cream' => $dark])>{{ $title }}</{{ $h }}>
    @if ($lead)<p @class(['text-[17px] leading-relaxed', 'text-muted' => !$dark, 'text-cream/80' => $dark])>{{ $lead }}</p>@endif
</div>
