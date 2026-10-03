@extends('layouts.site', ['title' => $minute->title, 'description' => 'Minutes of the committee meeting held on '.$minute->meeting_date->format('j F Y').'.'])
@section('content')
@include('partials.page-hero', ['eyebrow' => 'Minutes · '.$minute->meeting_date->format('j F Y'), 'heading' => $minute->title, 'crumbs' => [['About', route('about')], ['Minutes', null]]])
<article class="wrap prose-site max-w-3xl py-14">{!! \Illuminate\Support\Str::sanitizeHtml((string) $minute->body) !!}</article>
@endsection
