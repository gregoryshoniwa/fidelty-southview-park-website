<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Resolves the partner the user works for (X-Partner header picks one when several) and binds it. */
class EnsurePartnerUser
{
    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        $user = $request->user();
        $partners = $user?->partners()->where('active', true)->get() ?? collect();
        if ($partners->isEmpty()) {
            return response()->json(['message' => 'No partner access.'], 403);
        }
        $partner = $partners->firstWhere('id', (int) $request->header('X-Partner')) ?? $partners->first();
        if ($module && ! $partner->hasModule($module)) {
            return response()->json(['message' => 'This module is not enabled for your organisation.'], 403);
        }
        app()->instance(Partner::class, $partner);
        $request->attributes->set('partner', $partner);

        return $next($request);
    }
}
