<?php

namespace App\Actions\Auth;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ConfirmActionPassword
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function verify(Request $request): void
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $key = 'confirm:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = (int) ceil($seconds / 60);

            throw new ThrottleRequestsException("Demasiados intentos, espera {$minutes} minuto(s).", null, ['Retry-After' => $seconds]);
        }

        $password = $request->input('current_password');

        if (! is_string($password) || $password === '') {
            throw ValidationException::withMessages(['current_password' => 'Escribe tu contraseña para confirmar.']);
        }

        if (! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 300);
            $failures = (int) $request->session()->get('confirm_failures', 0) + 1;
            $request->session()->put('confirm_failures', $failures);
            $this->recordAudit->handle(AuditAction::AuthConfirmFailed, 'Contraseña incorrecta al confirmar una acción', actor: $user);

            if ($failures >= 10) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw new HttpResponseException(redirect()->route('login')->withErrors(['email' => 'Se cerró la sesión por demasiados intentos fallidos.']));
            }

            throw ValidationException::withMessages(['current_password' => 'La contraseña es incorrecta.']);
        }

        RateLimiter::clear($key);
    }
}
