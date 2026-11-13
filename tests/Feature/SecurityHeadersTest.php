<?php

use App\Models\User;

/** @return array<string, list<string>> */
function securityCspDirectives(string $policy): array
{
    $directives = [];
    foreach (explode(';', $policy) as $directive) {
        $tokens = preg_split('/\s+/', trim($directive));
        $name = array_shift($tokens);
        if ($name !== '') {
            $directives[$name] = $tokens;
        }
    }

    return $directives;
}

it('sends the security headers and restrictive CSP on login and authenticated client pages', function (string $route) {
    if ($route === 'clients.index') {
        $this->actingAs(User::factory()->create());
    }

    $response = $this->get(route($route))->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'same-origin')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $directives = securityCspDirectives($response->headers->get('Content-Security-Policy'));
    expect($directives['default-src'])->toBe(["'self'"]);
    expect($directives['script-src'])->toContain("'self'");
    expect($directives['style-src'])->toContain("'self'", "'unsafe-inline'");
    expect($directives['img-src'])->toBe(["'self'", 'data:', 'blob:']);
    expect($directives['font-src'])->toBe(["'self'", 'data:']);
    expect($directives['connect-src'])->toBe(["'self'"]);
    expect($directives['object-src'])->toBe(["'none'"]);
    expect($directives['frame-ancestors'])->toBe(["'none'"]);
    expect($directives['base-uri'])->toBe(["'self'"]);
    expect($directives['form-action'])->toBe(["'self'"]);
})->with(['login', 'clients.index']);

it('uses the response CSP nonce on Vite and inline scripts and generates a fresh nonce for each request', function () {
    $first = $this->get(route('login'))->assertOk();
    $firstPolicy = $first->headers->get('Content-Security-Policy');
    expect(preg_match("/'nonce-([^']+)'/", $firstPolicy, $matches))->toBe(1);
    $nonce = $matches[1];
    expect($nonce)->not->toBe('');
    preg_match_all('/<script\b([^>]*)>.*?<\/script>/s', $first->getContent(), $scripts, PREG_SET_ORDER);
    $executableScripts = array_filter($scripts, fn (array $script): bool => ! str_contains($script[1], 'type="application/json"'));
    expect($executableScripts)->not->toBeEmpty();
    foreach ($executableScripts as $script) {
        expect($script[1])->toContain('nonce="'.$nonce.'"');
    }

    $second = $this->get(route('login'))->assertOk();
    expect(preg_match("/'nonce-([^']+)'/", $second->headers->get('Content-Security-Policy'), $secondMatch))->toBe(1);
    expect($secondMatch[1])->not->toBe($nonce);
});

it('allows the local Vite origin and matching websocket only in local development', function (string $origin, string $websocket) {
    $hot = public_path('hot');
    $original = is_file($hot) ? file_get_contents($hot) : null;
    file_put_contents($hot, $origin.PHP_EOL);
    $this->app->detectEnvironment(fn () => 'local');

    try {
        $response = $this->get(route('login'))->assertOk();

        $directives = securityCspDirectives($response->headers->get('Content-Security-Policy'));
        expect($directives['script-src'])->toContain($origin);
        expect($directives['style-src'])->toContain($origin);
        expect($directives['connect-src'])->toContain($origin, $websocket);
    } finally {
        if ($original === null) {
            unlink($hot);
        } else {
            file_put_contents($hot, $original);
        }
    }
})->with([
    'HTTP dev server' => ['http://127.0.0.1:5173', 'ws://127.0.0.1:5173'],
    'HTTPS dev server' => ['https://localhost:5173', 'wss://localhost:5173'],
]);

it('does not relax CSP for a Vite hot file outside the local environment', function () {
    $hot = public_path('hot');
    $original = is_file($hot) ? file_get_contents($hot) : null;
    file_put_contents($hot, 'http://127.0.0.1:5173'.PHP_EOL);
    $this->app->detectEnvironment(fn () => 'production');

    try {
        $response = $this->get(route('login'))->assertOk();

        expect($response->headers->get('Content-Security-Policy'))->not->toContain('127.0.0.1:5173', 'ws://');
    } finally {
        if ($original === null) {
            unlink($hot);
        } else {
            file_put_contents($hot, $original);
        }
    }
});
