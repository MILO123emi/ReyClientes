<?php

namespace App\Http\Controllers;

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\SetUserPassword;
use App\Actions\Users\UpdateUserPermission;
use App\Http\Requests\Users\DestroyUserRequest;
use App\Http\Requests\Users\SetUserPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserPermissionRequest;
use App\Http\Resources\ManagedUserResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('users/Index', ['users' => ManagedUserResource::collection(User::query()->orderByDesc('is_owner')->orderBy('name')->orderBy('id')->paginate(20)), 'passwordRules' => Password::defaults()->toPasswordRulesString()]);
    }

    public function store(StoreUserRequest $request, CreateUser $action): RedirectResponse
    {
        $action->handle($request->validated('name'), $request->validated('email'), $request->validated('password'), false, $request->boolean('can_manage_cards'), true);

        return $this->done('Usuario creado');
    }

    public function updatePermission(UpdateUserPermissionRequest $request, User $user, UpdateUserPermission $action): RedirectResponse
    {
        $action->handle($user, $request->boolean('can_manage_cards'));

        return $this->done('Permiso actualizado');
    }

    public function setPassword(SetUserPasswordRequest $request, User $user, SetUserPassword $action): RedirectResponse
    {
        $action->handle($user, $request->validated('password'), true);

        return $this->done('Contraseña asignada');
    }

    public function destroy(DestroyUserRequest $request, User $user, DeleteUser $action): RedirectResponse
    {
        $action->handle($user);

        return $this->done('Usuario eliminado');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
