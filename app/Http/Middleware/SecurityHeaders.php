<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        // Jangan paksa upgrade ke HTTPS pada environment lokal, karena server dev (artisan serve) hanya melayani HTTP.
        $viteSources = app()->isLocal() ? ' http://localhost:5173 http://127.0.0.1:5173' : '';
        $connectSources = app()->isLocal() ? ' ws://localhost:5173 ws://127.0.0.1:5173' : '';
        $styleElementSources = app()->isLocal() ? " 'unsafe-inline'{$viteSources}" : '';
        $csp = "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; "
            ."script-src 'self' 'nonce-{$nonce}'{$viteSources}; "
            ."style-src 'self' 'nonce-{$nonce}'{$styleElementSources}; style-src-attr 'unsafe-inline'; "
            .'img-src \'self\' data: blob:; '
            .'font-src \'self\' data:; '
            ."connect-src 'self'{$connectSources}; "
            ."worker-src 'self' blob:; manifest-src 'self'";
        if (! app()->isLocal()) {
            $csp .= '; upgrade-insecure-requests';
        }

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
