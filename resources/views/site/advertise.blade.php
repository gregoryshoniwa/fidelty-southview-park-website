@extends('layouts.site', ['title' => 'Advertise with us', 'description' => 'Reach every verified household in Fidelity Southview Park with sponsored tiles, notices and banners.', 'breadcrumbs' => [['Home', url('/')], ['Advertise', route('advertise')]]])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Advertise', 'heading' => 'Reach every verified household in Southview Park', 'lead' => 'Few sponsors, high attention. Every placement is clearly labelled and never appears inside payment or document screens.', 'crumbs' => [['Advertise', null]]])
<div class="wrap grid gap-12 py-14 lg:grid-cols-[1fr_340px]">
    <div>
        @if ($page)<div class="prose-site mb-10">{!! $page->body !!}</div>@endif
        <div class="card overflow-x-auto">
            <table class="w-full min-w-[560px] text-left text-sm">
                <thead><tr class="border-b border-forest-900 text-xs uppercase tracking-wider text-muted"><th class="p-4">Placement</th><th class="p-4">Size</th><th class="p-4">Where</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($slots as $k => $s)
                        <tr><td class="p-4 font-bold text-forest-900">{{ $s['label'] }}</td><td class="p-4 text-muted">{{ $s['size'] }}</td><td class="p-4 text-muted">{{ ['hero_takeover' => 'Home page hero, one sponsor per week', 'billboard' => 'Home page under the services', 'medium_rect' => 'Notices and home sidebar', 'half_page' => 'Home sidebar, desktop only', 'sponsored_tile' => 'Community directory, one per category', 'sponsored_notice' => 'Notice feed, clearly marked', 'logo_strip' => 'Footer partner strip, yearly'][$k] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <aside class="card flex flex-col gap-4 self-start p-6">
        <h2 class="font-serif text-xl font-bold text-forest-900">Book a placement</h2>
        <p class="text-sm text-muted">Sign in and write to the committee with the placement, dates and your artwork. We reply within 48 hours with a quote.</p>
        <a href="/app/inbox/new?category=suggestion&subject=Advertising%20booking" class="btn btn-gold">Request a quote</a>
        <p class="text-xs text-muted">Rules: no political, gambling, alcohol or misleading adverts. The Secretary approves every creative.</p>
    </aside>
</div>
@endsection
