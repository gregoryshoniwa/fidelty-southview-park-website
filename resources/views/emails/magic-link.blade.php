@extends('emails.layout', ['preheader' => 'Your sign-in link. It works once and expires in 15 minutes.'])
@section('content')
<h1 class="h1" style="margin:0 0 12px;font-family:Georgia,'Times New Roman',serif;font-size:28px;line-height:1.2;color:#073320;">{{ $newAccount ? 'Welcome to Southview Park' : 'Sign in to Southview Park' }}</h1>
<p style="margin:0 0 6px;">{{ $newAccount ? 'Tap the button to confirm your email and create your free resident account.' : 'Tap the button to sign in. No password needed.' }}</p>
@include('emails.button', ['url' => $url, 'label' => $newAccount ? 'Confirm and continue' : 'Sign me in'])
<p style="margin:0 0 14px;font-size:14px;color:#4c5a52;">This link works once and expires in 15 minutes. Open it on the phone or computer you want to use.</p>
<p style="margin:0;font-size:13px;color:#4c5a52;">If the button doesn't work, copy this link into your browser:<br><a href="{{ $url }}" style="color:#0e4d2e;word-break:break-all;">{{ $url }}</a></p>
<p style="margin:22px 0 0;font-size:13px;color:#4c5a52;">Didn't ask for this? Ignore this email. Nobody can sign in without the link.</p>
@endsection
