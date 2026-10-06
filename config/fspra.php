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
    // Firebase Authentication, used only for phone SMS codes (Google sign-in uses Socialite, see config/services.php).
    // Phone SMS needs the Blaze plan: US$0.09 per SMS to Zimbabwe, first 10 per day free.
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'api_key' => env('FIREBASE_API_KEY'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
        'app_id' => env('FIREBASE_APP_ID'),
    ],
    // 'firebase' sends sign-in codes through Firebase; 'local' uses the SMS gateway in config('fspra.sms');
    // 'none' sends no SMS: numbers are proved on WhatsApp or confirmed by the committee.
    'phone_provider' => env('AUTH_PHONE_PROVIDER', 'local'),
    // Free phone proof: residents send "VERIFY 123456" to this WhatsApp Cloud API number (incoming messages are free).
    'whatsapp' => [
        'number' => env('WHATSAPP_NUMBER'),                   // e.g. 263771234567
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),       // any long random text; also typed into Meta's webhook form
        'app_secret' => env('WHATSAPP_APP_SECRET'),           // Meta app > App settings > Basic > App secret
        'token' => env('WHATSAPP_TOKEN'),                     // optional: lets us reply "Thank you, confirmed"
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'), // optional, with the token
        'channel_url' => env('WHATSAPP_CHANNEL_URL'),         // public channel link; shows the "Follow" buttons for notices
    ],
    'consent_version' => '2026-10-01',
    'documents' => ['max_kb' => 8192, 'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp']],
];
