<?php

namespace App\Http\Controllers;

use App\Actions\Account\ConfirmEmailChange;
use App\Actions\Account\RequestEmailChange;
use App\Http\Requests\Account\ConfirmEmailChangeRequest;
use App\Http\Requests\Account\RequestEmailChangeRequest;
use App\Models\EmailChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OwnerEmailController extends Controller
{
    public function edit(Request $request): Response
    {
        $pending = EmailChangeRequest::query()->where('user_id', $request->user()->id)->first();

        return Inertia::render('account/Email', [
            'email' => $request->user()->email,
            'pending' => $pending !== null && $pending->isUsable()
                ? ['email' => $pending->new_email, 'expiresAt' => $pending->expires_at->toIso8601String()]
                : null,
        ]);
    }

    public function store(RequestEmailChangeRequest $request, RequestEmailChange $requestChange): RedirectResponse
    {
        try {
            $requestChange->handle($request->user(), $request->validated('email'));
        } catch (Throwable) {
            Log::error('No se pudo enviar el código de cambio de correo.');

            throw ValidationException::withMessages(['email' => 'No se pudo enviar el código a ese correo. Revisa que esté bien escrito e intenta de nuevo.']);
        }

        return to_route('account.email');
    }

    public function confirm(ConfirmEmailChangeRequest $request, ConfirmEmailChange $confirm): RedirectResponse
    {
        $confirm->handle($request->user(), $request->validated('code'), $request->session()->getId());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Correo actualizado']);

        return to_route('account.email');
    }

    public function destroy(Request $request): RedirectResponse
    {
        EmailChangeRequest::query()->where('user_id', $request->user()->id)->delete();

        return to_route('account.email');
    }
}
