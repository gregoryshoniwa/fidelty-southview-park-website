<?php

use App\Http\Middleware\EnsurePartnerUser;
use App\Http\Middleware\EnsureVerifiedResident;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->append(SecurityHeaders::class);
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'verified.resident' => EnsureVerifiedResident::class,
            'partner' => EnsurePartnerUser::class,
            'payments.live' => \App\Http\Middleware\PaymentsLive::class,
        ]);
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->redirectGuestsTo(fn (Request $r) => $r->is('partner', 'partner/*') ? '/partner/login' : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
