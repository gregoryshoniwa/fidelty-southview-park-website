@extends('layouts.site', ['title' => $page->title, 'description' => $page->meta_description ?? \Illuminate\Support\Str::limit(strip_tags($page->body), 155)])
@section('content')
@include('partials.page-hero', ['heading' => $page->title, 'lead' => 'Last updated '.$page->updated_at->format('j F Y'), 'crumbs' => [[$page->title, null]]])
<article class="wrap prose-site max-w-3xl py-14">{!! $page->body !!}</article>
@endsection
