<?php

return [
    'site_domain' => env('SITE_DOMAIN', 'fidelity-southview.co.zw'),
    'id_hash_salt' => env('ID_HASH_SALT', ''),
    'otp' => ['ttl_minutes' => 10, 'max_attempts' => 5, 'length' => 6],
    'sms' => ['driver' => env('SMS_DRIVER', 'log'), 'sender_id' => env('SMS_SENDER_ID', 'FSPRA'), 'quiet_start' => 21, 'daily_cap' => (int) env('SMS_DAILY_CAP', 1500), 'quiet_end' => 7,
        'url' => env('SMS_API_URL'), 'key' => env('SMS_API_KEY')],
    'fidelity' => ['driver' => env('FIDELITY_DRIVER', 'fake'), 'base_url' => env('FIDELITY_BASE_URL'), 'api_key' => env('FIDELITY_API_KEY')],
    // Bill payments stay 'coming soon' until the TN CyberTech gateway is integrated.
    'payments_live' => (bool) env('PAYMENTS_LIVE', false),
    'tncb' => ['driver' => env('TNCB_DRIVER', 'fake'), 'base_url' => env('TNCB_BASE_URL'), 'merchant_id' => env('TNCB_MERCHANT_ID'),
        'api_key' => env('TNCB_API_KEY'), 'webhook_secret' => env('TNCB_WEBHOOK_SECRET')],
    'gemini' => ['api_key' => env('GEMINI_API_KEY'), 'live_model' => env('GEMINI_LIVE_MODEL', 'gemini-live-2.5-flash-preview'),
        'text_model' => env('GEMINI_TEXT_MODEL', 'gemini-2.5-flash'), 'daily_token_cap' => (int) env('ASSISTANT_DAILY_TOKEN_CAP', 2000000), 'daily_live_sessions' => (int) env('ASSISTANT_DAILY_VOICE_SESSIONS', 150)],
    // Firebase Authentication: Google and email sign-in are free (Spark plan, 50k monthly users).
    // Phone SMS needs the Blaze plan: US$0.09 per SMS to Zimbabwe, first 10 per day free.
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'api_key' => env('FIREBASE_API_KEY'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
        'app_id' => env('FIREBASE_APP_ID'),
        'providers' => array_filter(explode(',', (string) env('FIREBASE_PROVIDERS', 'google,email'))),
    ],
    // 'firebase' sends sign-in codes through Firebase; 'local' uses the SMS gateway in config('fspra.sms').
    'phone_provider' => env('AUTH_PHONE_PROVIDER', 'local'),
    'consent_version' => '2026-10-01',
    'documents' => ['max_kb' => 8192, 'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp']],
];
