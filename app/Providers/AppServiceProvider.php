<?php

namespace App\Providers;

use App\Integrations\Fidelity\FakeFidelityClient;
use App\Integrations\Fidelity\FidelityClient;
use App\Integrations\Fidelity\HttpFidelityClient;
use App\Integrations\Sms\HttpSmsGateway;
use App\Integrations\Sms\LogSmsGateway;
use App\Integrations\Sms\SmsGateway;
use App\Integrations\Tncb\FakeGateway;
use App\Integrations\Tncb\HttpGateway;
use App\Integrations\Tncb\PaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('csp-nonce', '');
        $this->app->bind(FidelityClient::class, fn () => match (config('fspra.fidelity.driver')) {
            'http' => new HttpFidelityClient,
            'manual' => new \App\Integrations\Fidelity\ManualFidelityClient,
            default => new FakeFidelityClient,
        });
        $this->app->bind(PaymentGateway::class, fn () => config('fspra.tncb.driver') === 'http' ? new HttpGateway : new FakeGateway);
        $this->app->bind(SmsGateway::class, fn () => config('fspra.sms.driver') === 'http' ? new HttpSmsGateway : new LogSmsGateway);
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
            if (! $this->app->runningInConsole() && strlen((string) config('fspra.id_hash_salt')) < 32) {
                throw new \RuntimeException('Set ID_HASH_SALT to a random value of at least 32 characters before running in production.');
            }
            if (! $this->app->runningInConsole() && config('fspra.payments_live') && strlen((string) config('fspra.tncb.webhook_secret')) < 32) {
                throw new \RuntimeException('Set TNCB_WEBHOOK_SECRET (32+ characters) before turning payments on.');
            }
        }

        RateLimiter::for('otp', fn (Request $r) => [
            Limit::perHour(5)->by('otp-phone:'.(\App\Services\Phone::normalise((string) $r->input('phone')) ?? 'invalid')),
            Limit::perDay(10)->by('otp-phone-day:'.(\App\Services\Phone::normalise((string) $r->input('phone')) ?? 'invalid')),
            Limit::perHour(20)->by('otp-ip:'.$r->ip()),
        ]);
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(90)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('payments', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('assistant', fn (Request $r) => [Limit::perMinute(12)->by($r->user()?->id ?: $r->ip()), Limit::perDay(200)->by($r->ip())]);
        RateLimiter::for('forms', fn (Request $r) => Limit::perMinute(6)->by($r->ip()));
    }
}
