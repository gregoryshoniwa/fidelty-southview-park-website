@props(['ad' => null, 'placement' => 'medium_rect', 'class' => ''])
@php
    $sizes = ['billboard' => 'aspect-[970/250] max-w-[970px] max-sm:aspect-[320/100]', 'medium_rect' => 'aspect-[300/250] w-[300px] max-w-full', 'half_page' => 'aspect-[300/600] w-[300px]'];
    $house = [
        'billboard' => ['headline' => 'Your stand. Your documents. Two minutes.', 'body' => 'Verify with Fidelity Life records and download your Agreement of Sale from your phone.', 'cta' => 'Verify my stand', 'url' => '/app/verify', 'image' => '/images/ads/house-billboard.webp'],
        'medium_rect' => ['headline' => 'Advertise to every verified household', 'body' => 'Sponsored tiles, notices and banners from US$25 a month.', 'cta' => 'See rates', 'url' => '/advertise', 'image' => '/images/ads/house-rect.webp'],
        'half_page' => ['headline' => 'Official notices by SMS', 'body' => 'Dated, signed and calm. No group chats.', 'cta' => 'Subscribe', 'url' => '/notices#subscribe', 'image' => '/images/ads/house-half.webp'],
    ][$placement] ?? null;
    $headline = $ad?->headline ?? $house['headline'] ?? '';
    $body = $ad?->body ?? $house['body'] ?? '';
    $cta = $ad?->cta_label ?? $house['cta'] ?? 'Learn more';
    $href = $ad ? route('ad.click', $ad) : ($house['url'] ?? '#');
    $image = $ad?->creative_path ? asset($ad->creative_path) : ($house['image'] ?? null);
    $label = $ad && $ad->advertiser !== 'Southview Park Residents Association' ? 'Sponsored' : 'From the association';
@endphp
<aside {{ $attributes->merge(['class' => 'flex flex-col items-center gap-1.5 '.$class]) }} aria-label="{{ $label }}">
    <span class="sponsored-label">{{ $label }}</span>
    <a href="{{ $href }}" @if($ad) rel="sponsored noopener" data-ad="{{ $ad->id }}" @endif class="group relative block w-full overflow-hidden rounded-[8px] border border-line bg-forest-900 {{ $sizes[$placement] ?? "" }}">
        @if ($image)
            <img src="{{ $image }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-[1.02]">
        @endif
        <span class="sr-only">{{ $headline }}. {{ $body }} {{ $cta }}</span>
    </a>
</aside>
