@extends('layouts.site', ['title' => '500', 'noindex' => true])
@section('content')
@php $copy = [403 => ['Not allowed', 'You do not have access to this page.'], 404 => ['Page not found', 'The page you were looking for has moved or never existed.'], 419 => ['Page expired', 'Your session timed out. Go back and try again.'], 429 => ['Slow down a little', 'Too many attempts in a short time. Wait a minute and try again.'], 500 => ['Something went wrong', 'We have been told and are fixing it. Please try again shortly.'], 503 => ['Back soon', 'The site is down for a short update.']][500]; @endphp
<section class="wrap flex min-h-[60vh] flex-col items-center justify-center gap-4 py-20 text-center">
    <p class="font-serif text-7xl font-bold text-gold-500">500</p>
    <h1 class="font-serif text-3xl font-bold text-forest-900">{{ $copy[0] }}</h1>
    <p class="max-w-md text-muted">{{ $copy[1] }}</p>
    <div class="mt-2 flex gap-3"><a href="/" class="btn btn-gold">Go home</a><a href="/faq" class="btn btn-outline">Get help</a></div>
</section>
@endsection
