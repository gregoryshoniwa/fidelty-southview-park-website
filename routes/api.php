<?php

use App\Http\Controllers\Api;
use App\Http\Controllers\Partner;
use App\Http\Controllers\Web\GoogleAuthController;
use Illuminate\Support\Facades\Route;

// ---------- Public ----------
Route::post('/auth/otp', [Api\AuthController::class, 'requestOtp'])->middleware('throttle:otp');
Route::post('/auth/verify', [Api\AuthController::class, 'verifyOtp'])->middleware('throttle:20,1');
Route::get('/auth/config', [Api\AuthController::class, 'config']);
Route::post('/auth/email', [Api\EmailLoginController::class, 'request'])->middleware('throttle:email-link');
Route::post('/auth/email/verify', [Api\EmailLoginController::class, 'verify'])->middleware('throttle:20,1');
Route::get('/auth/google/pending', [GoogleAuthController::class, 'pending']);
Route::post('/auth/google/complete', [GoogleAuthController::class, 'complete'])->middleware('throttle:20,1');
Route::post('/auth/whatsapp', [Api\PhoneVerifyController::class, 'startLogin'])->middleware('throttle:10,10');
Route::post('/auth/whatsapp/complete', [Api\PhoneVerifyController::class, 'complete'])->middleware('throttle:20,1');
Route::get('/phone/whatsapp/{id}', [Api\PhoneVerifyController::class, 'status'])->middleware('throttle:90,1');
Route::post('/auth/firebase', [Api\AuthController::class, 'firebase'])->middleware('throttle:20,1');
Route::get('/assistant/config', [Api\AssistantController::class, 'config']);
Route::post('/assistant/chat', [Api\AssistantController::class, 'chat'])->middleware('throttle:assistant');
Route::post('/assistant/transcript', [Api\AssistantController::class, 'transcript'])->middleware('throttle:assistant');
Route::get('/billers', [Api\PaymentController::class, 'billers']);
Route::post('/payments/notify-me', [Api\PaymentController::class, 'notifyMe'])->middleware('auth:sanctum');

// ---------- Signed-in residents ----------
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/me', [Api\AuthController::class, 'me']);
    Route::post('/auth/logout', [Api\AuthController::class, 'logout']);
    Route::patch('/me', [Api\AccountController::class, 'update']);
    Route::post('/me/phone/otp', [Api\AuthController::class, 'linkPhoneOtp'])->middleware('throttle:otp');
    Route::post('/me/phone', [Api\AuthController::class, 'linkPhone'])->middleware('throttle:20,60');
    Route::post('/me/phone/whatsapp', [Api\PhoneVerifyController::class, 'startLink'])->middleware('throttle:10,10');
    Route::post('/me/phone/unconfirmed', [Api\PhoneVerifyController::class, 'unconfirmed'])->middleware('throttle:10,10');
    Route::get('/me/export', [Api\AccountController::class, 'export']);
    Route::post('/me/delete-request', [Api\AccountController::class, 'requestDeletion']);
    Route::post('/assistant/escalate', [Api\AssistantController::class, 'escalate']);
    Route::post('/assistant/live', [Api\AssistantController::class, 'live'])->middleware('throttle:5,1440');

    Route::post('/verify/start', [Api\VerifyController::class, 'start'])->middleware('throttle:10,60');
    Route::post('/verify/confirm', [Api\VerifyController::class, 'confirm'])->middleware('throttle:20,60');

    Route::get('/notices', [Api\CommunityController::class, 'notices']);
    Route::get('/notifications', [Api\CommunityController::class, 'notifications']);
    Route::post('/notifications/read', [Api\CommunityController::class, 'readNotifications']);
    Route::get('/threads', [Api\ThreadController::class, 'index']);
    Route::post('/threads', [Api\ThreadController::class, 'store'])->middleware('throttle:10,60');
    Route::get('/threads/{thread}', [Api\ThreadController::class, 'show']);
    Route::post('/threads/{thread}/messages', [Api\ThreadController::class, 'reply'])->middleware('throttle:30,1');
    Route::get('/pages', [Api\CommunityController::class, 'pages']);
    Route::get('/pages/{page}', [Api\CommunityController::class, 'page']);
    Route::post('/pages/{page}/follow', [Api\CommunityController::class, 'follow']);
    Route::delete('/pages/{page}/follow', [Api\CommunityController::class, 'unfollow']);
    Route::post('/posts/{post}/report', [Api\CommunityController::class, 'postReport'])->middleware('throttle:10,60');

    Route::middleware('verified.resident')->group(function () {
        Route::get('/fidelity/payments', [Api\FidelityController::class, 'payments']);
        Route::get('/fidelity/agreement', [Api\FidelityController::class, 'agreement']);
        Route::post('/fidelity/agreement/replace', [Api\FidelityController::class, 'replace'])->middleware('throttle:5,60');

        Route::get('/requests', [Api\RequestController::class, 'index']);
        Route::get('/law-firms', [Api\RequestController::class, 'lawFirms']);
        Route::post('/services/{service}/requests', [Api\RequestController::class, 'store'])->middleware('throttle:10,60');
        Route::get('/requests/{serviceRequest}', [Api\RequestController::class, 'show']);
        Route::post('/requests/{serviceRequest}/documents', [Api\RequestController::class, 'upload'])->middleware('throttle:30,60');
        Route::post('/requests/{serviceRequest}/messages', [Api\RequestController::class, 'message'])->middleware('throttle:30,1');
        Route::get('/documents/{document}', [Api\RequestController::class, 'document']);

        Route::post('/payments/quote', [Api\PaymentController::class, 'quote'])->middleware('payments.live');
        Route::post('/payments', [Api\PaymentController::class, 'store'])->middleware(['payments.live', 'throttle:payments']);
        Route::get('/payments', [Api\PaymentController::class, 'index']);
        Route::get('/payments/{payment}', [Api\PaymentController::class, 'show']);
        Route::get('/payments/{payment}/receipt', [Api\PaymentController::class, 'receipt']);

        Route::get('/polls', [Api\CommunityController::class, 'polls']);
        Route::post('/polls/{poll}/vote', [Api\CommunityController::class, 'vote'])->middleware('throttle:10,1');
        Route::post('/pages/{page}/orders', [Api\CommunityController::class, 'order'])->middleware(['payments.live', 'throttle:payments']);
        Route::get('/school-invoices', [Api\CommunityController::class, 'invoices']);
        Route::post('/school-invoices/{invoice}/pay', [Api\CommunityController::class, 'payInvoice'])->middleware(['payments.live', 'throttle:payments']);
        Route::get('/security', [Api\CommunityController::class, 'security']);
        Route::post('/incidents', [Api\CommunityController::class, 'reportIncident'])->middleware('throttle:10,60');
    });
});

// ---------- Partner portal ----------
Route::prefix('partner')->group(function () {
    Route::post('/auth/password', [Partner\AuthController::class, 'password'])->middleware('throttle:partner-login');
    Route::post('/auth/otp', [Partner\AuthController::class, 'otp'])->middleware('throttle:20,1');

    Route::middleware(['auth:sanctum', 'throttle:api', 'partner'])->group(function () {
        Route::get('/me', [Partner\PortalController::class, 'me']);
        Route::get('/stats', [Partner\PortalController::class, 'stats']);
        Route::get('/requests', [Partner\PortalController::class, 'queue'])->middleware('partner:queue');
        Route::get('/requests/{serviceRequest}', [Partner\PortalController::class, 'show'])->middleware('partner:queue');
        Route::patch('/requests/{serviceRequest}', [Partner\PortalController::class, 'update'])->middleware('partner:status_updates');
        Route::post('/requests/{serviceRequest}/messages', [Partner\PortalController::class, 'startThread'])->middleware('partner:messages');
        Route::get('/documents/{document}', [Partner\PortalController::class, 'document'])->middleware('partner:documents');
        Route::get('/threads', [Partner\PortalController::class, 'threads'])->middleware('partner:messages');
        Route::get('/threads/{thread}', [Partner\PortalController::class, 'thread'])->middleware('partner:messages');
        Route::post('/threads/{thread}/messages', [Partner\PortalController::class, 'reply'])->middleware(['partner:messages', 'throttle:60,1']);
        Route::get('/broadcasts', [Partner\PortalController::class, 'broadcasts'])->middleware('partner:broadcast');
        Route::post('/broadcasts', [Partner\PortalController::class, 'broadcast'])->middleware(['partner:broadcast', 'throttle:5,60']);
        Route::get('/settlements', [Partner\PortalController::class, 'settlements'])->middleware('partner:settlements');
        Route::get('/invoices', [Partner\PortalController::class, 'invoices'])->middleware('partner:invoices');
        Route::post('/invoices', [Partner\PortalController::class, 'storeInvoice'])->middleware(['partner:invoices', 'throttle:60,60']);
        Route::get('/incidents', [Partner\PortalController::class, 'incidents'])->middleware('partner:incidents');
        Route::patch('/incidents/{incident}', [Partner\PortalController::class, 'updateIncident'])->middleware('partner:incidents');
        Route::get('/export', [Partner\PortalController::class, 'export'])->middleware(['partner:export', 'throttle:10,60']);
    });
});
