<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();
        $nonce = Vite::cspNonce();
        $scripts = ["'self'", "'nonce-{$nonce}'"];
        $styles = ["'self'", "'unsafe-inline'"];
        $images = ["'self'", 'data:', 'blob:'];
        $fonts = ["'self'", 'data:'];
        $connections = ["'self'"];
        $devServer = $this->developmentServer();
        if ($devServer !== null) {
            $scripts[] = $devServer['origin'];
            $styles[] = $devServer['origin'];
            $images[] = $devServer['origin'];
            $fonts[] = $devServer['origin'];
            array_push($connections, $devServer['origin'], $devServer['websocket']);
        }

        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scripts),
            'style-src '.implode(' ', $styles),
            'img-src '.implode(' ', $images),
            'font-src '.implode(' ', $fonts),
            'connect-src '.implode(' ', $connections),
            "object-src 'none'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]));

        return $response;
    }

    /** @return array{origin: string, websocket: string}|null */
    private function developmentServer(): ?array
    {
        $hotFile = public_path('hot');
        if (! app()->environment('local') || ! is_file($hotFile)) {
            return null;
        }

        $contents = file_get_contents($hotFile);
        $url = $contents === false ? '' : trim($contents);
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        $authority = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return [
            'origin' => $parts['scheme'].'://'.$authority,
            'websocket' => ($parts['scheme'] === 'https' ? 'wss' : 'ws').'://'.$authority,
        ];
    }
}
