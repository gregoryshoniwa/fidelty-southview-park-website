{{-- Branded, table-based email layout: works in Gmail, Outlook and phone mail apps. --}}
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light only"><title>{{ $subject ?? 'Southview Park Residents' }}</title>
<style>
  @media (max-width:600px){ .container{width:100%!important} .px{padding-left:20px!important;padding-right:20px!important} .h1{font-size:24px!important} }
  a{color:#0e4d2e}
</style>
</head>
<body style="margin:0;padding:0;background:#ece4d0;-webkit-text-size-adjust:100%;">
@isset($preheader)<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>@endisset
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ece4d0;">
<tr><td align="center" style="padding:28px 12px;">
  <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">
    <tr><td style="background:#073320;border-radius:14px 14px 0 0;padding:22px 32px;" class="px">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
        <td style="padding-right:14px;"><img src="{{ isset($message) ? $message->embed(public_path('images/email-logo.png')) : asset('images/email-logo.png') }}" width="64" height="64" alt="Fidelity Southview Park Residents Association" style="display:block;border:0;border-radius:10px;background:#ffffff;"></td>
        <td style="font-family:Georgia,'Times New Roman',serif;color:#faf7f0;font-size:19px;font-weight:bold;line-height:1.2;">Fidelity Southview Park<br><span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:2px;color:#c9a227;font-weight:bold;">RESIDENTS ASSOCIATION</span></td>
      </tr></table>
    </td></tr>
    <tr><td style="height:4px;background:#c9a227;font-size:0;line-height:0;">&nbsp;</td></tr>
    <tr><td style="background:#ffffff;padding:34px 32px 30px;font-family:Arial,Helvetica,sans-serif;color:#14201a;font-size:16px;line-height:1.6;" class="px">
      @yield('content')
    </td></tr>
    <tr><td style="background:#faf7f0;border-radius:0 0 14px 14px;padding:22px 32px;font-family:Arial,Helvetica,sans-serif;color:#4c5a52;font-size:12px;line-height:1.6;border-top:1px solid #e6e1d6;" class="px">
      <strong style="color:#073320;">Stronger Together. A Better Community.</strong><br>
      Fidelity Southview Park Residents Association · Amalinda, Harare ·
      <a href="{{ url('/') }}" style="color:#0e4d2e;">{{ config('fspra.site_domain') }}</a><br>
      @isset($footerNote){{ $footerNote }}<br>@endisset
      We never ask for your password, ID number or sign-in codes by email.
      <a href="{{ url('/app/settings') }}" style="color:#0e4d2e;">Email settings</a> · <a href="{{ url('/privacy') }}" style="color:#0e4d2e;">Privacy</a>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
