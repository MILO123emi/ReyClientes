<?php

namespace App\Http\Controllers;

use App\Actions\Recovery\SendOwnerResetCode;
use App\Actions\Recovery\VerifyOwnerResetCode;
use App\Http\Requests\Recovery\SendOwnerResetCodeRequest;
use App\Http\Requests\Recovery\VerifyOwnerResetCodeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

use function Illuminate\Support\defer;

class OwnerPasswordResetController extends Controller
{
    public const STATUS = 'Si el correo corresponde a la cuenta principal, te enviamos un código de 6 dígitos. Si eres otro usuario, pídele a la administradora que cambie tu contraseña.';

    public function request(Request $request): Response
    {
        return Inertia::render('auth/RecoverRequest', ['status' => $request->session()->get('status')]);
    }

    public function send(SendOwnerResetCodeRequest $request, SendOwnerResetCode $send): RedirectResponse
    {
        $email = $request->validated('email');
        $request->session()->put('recovery.email', $email);
        $request->session()->put('recovery.sent_at', now()->toIso8601String());

        // Sent after the response so its timing does not reveal whether the email belongs to the owner.
        defer(function () use ($send, $email): void {
            try {
                $send->handle($email);
            } catch (Throwable) {
                Log::error('No se pudo enviar el correo de recuperación.');
            }
        });

        return to_route('recovery.verify')->with('status', self::STATUS);
    }

    public function showVerify(Request $request): Response|RedirectResponse
    {
        if (! is_string($request->session()->get('recovery.email'))) {
            return to_route('recovery.request');
        }

        return Inertia::render('auth/RecoverVerify', [
            'status' => $request->session()->get('status'),
            'sentAt' => $request->session()->get('recovery.sent_at'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function verify(VerifyOwnerResetCodeRequest $request, VerifyOwnerResetCode $verify): RedirectResponse
    {
        $email = $request->session()->get('recovery.email');
        if (! is_string($email)) {
            return to_route('recovery.request');
        }

        $verify->handle($email, $request->validated('code'), $request->validated('password'));
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', 'Tu contraseña se cambió. Inicia sesión.');
    }
}
