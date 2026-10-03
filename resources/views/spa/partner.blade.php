<!DOCTYPE html>
<html lang="en-ZW">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Partner portal | Southview Park Residents</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#073320">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">
    @vite(['resources/css/app.css', 'resources/js/partner/main.js'])
</head>
<body class="bg-sand">
    <noscript><p style="padding:24px;font-family:sans-serif">Please enable JavaScript to use Partner portal.</p></noscript>
    <div id="app" data-payments-live="{{ config('fspra.payments_live') ? '1' : '0' }}" data-env="{{ app()->environment() }}"></div>
</body>
</html>
