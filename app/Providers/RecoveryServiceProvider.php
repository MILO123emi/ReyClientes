<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class RecoveryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('recovery-send', function (Request $request): Limit {
            $email = $request->input('email', $request->session()->get('recovery.email'));
            $email = is_string($email) ? Str::lower(trim($email)) : '';

            return Limit::perMinutes(15, 3)->by(hash('sha256', $email.'|'.$request->ip()))
                ->response(fn (Request $request, array $headers) => response()->json(['message' => 'Demasiados intentos. Espera 15 minutos para pedir otro código.'], 429, $headers));
        });

        RateLimiter::for('recovery-verify', fn (Request $request): Limit => Limit::perMinutes(15, 10)->by((string) $request->ip())
            ->response(fn (Request $request, array $headers) => response()->json(['message' => 'Demasiados intentos. Espera 15 minutos para verificar otro código.'], 429, $headers)));
    }
}
