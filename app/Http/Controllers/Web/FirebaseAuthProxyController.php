<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Serves Firebase's sign-in helper pages from our own domain (Firebase "proxy" option),
 * so Google's consent screen names fidelity-southview.co.zw instead of *.firebaseapp.com.
 * Set FIREBASE_AUTH_DOMAIN=fidelity-southview.co.zw in production to use it.
 */
class FirebaseAuthProxyController extends Controller
{
    public function __invoke(Request $request, string $path = '')
    {
        $project = (string) config('fspra.firebase.project_id');
        abort_if($project === '' || ! preg_match('#^[A-Za-z0-9._/-]*$#', $path) || str_contains($path, '..'), 404);

        // Firebase Hosting would serve this; we have no Hosting site, so answer from our own config.
        if ($path === 'firebase/init.json') {
            return response()->json([
                'apiKey' => config('fspra.firebase.api_key'),
                'authDomain' => config('fspra.firebase.auth_domain'),
                'projectId' => $project,
                'appId' => config('fspra.firebase.app_id'),
            ])->header('Cache-Control', 'public, max-age=3600');
        }

        $upstream = 'https://'.$project.'.firebaseapp.com/__/'.$path;
        $res = Http::withHeaders(array_filter([
            'Accept' => $request->header('Accept'),
            'Accept-Language' => $request->header('Accept-Language'),
            'User-Agent' => $request->userAgent(),
        ]))->timeout(15)->withOptions(['allow_redirects' => false])
            ->send($request->method(), $upstream, [
                'query' => $request->query(),
                'body' => in_array($request->method(), ['POST', 'PUT'], true) ? $request->getContent() : null,
                'headers' => array_filter(['Content-Type' => $request->header('Content-Type')]),
            ]);

        $headers = [];
        foreach (['Content-Type', 'Cache-Control', 'Location', 'Expires', 'Last-Modified', 'ETag'] as $h) {
            if ($v = $res->header($h)) {
                $headers[$h] = $v;
            }
        }

        return response($res->body(), $res->status(), $headers);
    }
}
