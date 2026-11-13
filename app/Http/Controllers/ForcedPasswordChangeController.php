<?php

namespace App\Http\Controllers;

use App\Actions\Users\ChangeOwnPassword;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForcedPasswordChangeController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('auth/ChangePassword', [
            'mustChange' => $request->user()->must_change_password,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(ChangePasswordRequest $request, ChangeOwnPassword $changePassword): RedirectResponse
    {
        $changePassword->handle($request->user(), $request->validated('password'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contraseña actualizada']);

        return to_route('clients.index');
    }
}
