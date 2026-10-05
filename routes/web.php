<?php

use App\Http\Controllers\Web\AdController;
use App\Http\Controllers\Web\DevCheckoutController;
use App\Http\Controllers\Web\SiteController;
use App\Http\Controllers\Web\SpaController;
use App\Http\Controllers\Web\WebhookController;
use Illuminate\Support\Facades\Route;

Route::controller(SiteController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/services', 'services')->name('services');
    Route::get('/services/{service}', 'service')->name('service');
    Route::get('/notices', 'notices')->name('notices');
    Route::get('/notices/{notice}', 'notice')->name('notice');
    Route::get('/community', 'community')->name('community');
    Route::get('/community/{page}', 'page')->name('community.page');
    Route::get('/about', 'about')->name('about');
    Route::get('/about/minutes/{minute}', 'minute')->name('minute');
    Route::get('/constitution', 'constitution')->name('constitution');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/advertise', 'advertise')->name('advertise');
    Route::get('/fees', 'fees')->name('fees');
    Route::get('/privacy', fn () => app(SiteController::class)->cms('privacy'))->name('privacy');
    Route::get('/terms', fn () => app(SiteController::class)->cms('terms'))->name('terms');
    Route::get('/complaints', fn () => app(SiteController::class)->cms('complaints'))->name('complaints');
    Route::post('/subscribe', 'subscribe')->middleware('throttle:forms')->name('subscribe');
    Route::get('/sitemap.xml', 'sitemap')->name('sitemap');
    Route::get('/robots.txt', 'robots');
    Route::get('/manifest.webmanifest', 'manifest');
});

// Firebase sign-in helper pages served from our own domain (see FirebaseAuthProxyController).
Route::match(['GET', 'POST'], '/__/{path}', \App\Http\Controllers\Web\FirebaseAuthProxyController::class)
    ->where('path', '(auth|firebase)/.*')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])->middleware('throttle:120,1');

Route::get('/go/ad/{sponsorship}', [AdController::class, 'click'])->name('ad.click');
Route::post('/api/ads/impressions', [AdController::class, 'impressions'])->middleware('throttle:60,1');

Route::post('/webhooks/tncb', [WebhookController::class, 'tncb'])->middleware('throttle:120,1')->name('webhooks.tncb');

if (config('fspra.tncb.driver') === 'fake' && ! app()->isProduction()) {
    Route::get('/dev/checkout/{payment}', [DevCheckoutController::class, 'show'])->middleware('signed')->name('dev.checkout');
    Route::post('/dev/checkout/{payment}', [DevCheckoutController::class, 'pay'])->middleware('signed')->name('dev.checkout.pay');
}

Route::redirect('/login', '/app/login');
Route::get('/app/{any?}', [SpaController::class, 'resident'])->where('any', '.*')->name('app');
Route::get('/partner/{any?}', [SpaController::class, 'partner'])->where('any', '.*')->name('partner');
