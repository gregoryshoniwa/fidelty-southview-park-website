@extends('emails.layout', ['preheader' => 'Your partner portal sign-in code. It expires in '.config('fspra.otp.ttl_minutes').' minutes.'])
@section('content')
<h1 class="h1" style="margin:0 0 12px;font-family:Georgia,'Times New Roman',serif;font-size:28px;line-height:1.2;color:#073320;">Your sign-in code</h1>
<p style="margin:0 0 18px;">Hello {{ $name }}, enter this code in the partner portal to finish signing in.</p>
<p style="margin:0 0 18px;"><span style="display:inline-block;padding:14px 22px;border-radius:8px;background:#e8f3ec;font-family:'Courier New',monospace;font-size:30px;font-weight:700;letter-spacing:8px;color:#073320;">{{ $code }}</span></p>
<p style="margin:0 0 14px;font-size:14px;color:#4c5a52;">It works once and expires in {{ config('fspra.otp.ttl_minutes') }} minutes.</p>
<p style="margin:22px 0 0;font-size:13px;color:#4c5a52;">Didn't try to sign in? Someone may know your password. Change it, and tell the committee at info@fidelity-southview.co.zw.</p>
@endsection
