<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(32);
        Vite::useCspNonce($nonce);
        app()->instance('csp-nonce', $nonce);

        $response = $next($request);

        if (function_exists('header_remove')) {
            @header_remove('X-Powered-By');
        }
        $response->headers->remove('X-Powered-By');

        $dev = app()->environment('local') && file_exists(public_path('hot'));
        $viteOrigin = $dev ? trim((string) @file_get_contents(public_path('hot'))) : '';
        $viteWs = $dev ? preg_replace('#^http#', 'ws', $viteOrigin) : '';

        $isAdmin = $request->is('admin', 'admin/*', 'livewire*', 'filament/*');
        $script = $isAdmin
            ? "'self' 'unsafe-inline' 'unsafe-eval'"
            : "'self' 'nonce-{$nonce}' 'strict-dynamic'";

        $csp = implode('; ', array_filter([
            "default-src 'self'",
            "script-src {$script} {$viteOrigin}",
            "style-src 'self' 'unsafe-inline' {$viteOrigin}",
            "img-src 'self' data: blob:",
            "font-src 'self' data: {$viteOrigin}",
            "connect-src 'self' https://generativelanguage.googleapis.com wss://generativelanguage.googleapis.com {$viteOrigin} {$viteWs}",
            "media-src 'self' blob:",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            app()->isProduction() ? 'upgrade-insecure-requests' : null,
        ]));

        $headers = [
            'Content-Security-Policy' => preg_replace('/\s+/', ' ', $csp),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(self), geolocation=(self), payment=(), usb=(), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];
        if ($request->isSecure() || app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains; preload';
        }
        foreach ($headers as $k => $v) {
            $response->headers->set($k, $v);
        }
        if ($request->is('api/*', 'app', 'app/*', 'partner', 'partner/*', 'admin', 'admin/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            if (! $response->headers->has('Cache-Control') || $request->is('api/*')) {
                $response->headers->set('Cache-Control', 'no-store, private');
            }
        }

        return $response;
    }
}
