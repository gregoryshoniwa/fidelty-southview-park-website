<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedResident
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->resident?->isVerified()) {
            return response()->json(['message' => 'Verify your stand first.', 'code' => 'verification_required'], 403);
        }

        return $next($request);
    }
}
