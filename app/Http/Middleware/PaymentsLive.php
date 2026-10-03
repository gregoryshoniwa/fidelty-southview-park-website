<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentsLive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('fspra.payments_live')) {
            return response()->json([
                'message' => 'Online payments are coming soon. We will notify you as soon as you can pay here.',
                'code' => 'payments_coming_soon',
            ], 503);
        }

        return $next($request);
    }
}
