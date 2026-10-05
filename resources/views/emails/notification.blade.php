@extends('emails.layout', ['preheader' => $body ?? $title, 'footerNote' => 'You get these emails because email updates are on in your Southview settings.'])
@section('content')
<h1 class="h1" style="margin:0 0 12px;font-family:Georgia,'Times New Roman',serif;font-size:26px;line-height:1.25;color:#073320;">{{ $title }}</h1>
@if($body)<p style="margin:0 0 6px;white-space:pre-line;">{{ $body }}</p>@endif
@if($url)@include('emails.button', ['url' => $url, 'label' => $cta ?? 'Open in the app'])@endif
<p style="margin:0;font-size:13px;color:#4c5a52;">Hi {{ $name }}, this is an automatic message from your residents association. Replies to this email are read by the committee.</p>
@endsection
