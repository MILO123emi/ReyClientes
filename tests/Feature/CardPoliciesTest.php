<?php

use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('enforces card permissions for every role model and operation', function (string $role, string $model, string $ability) {
    $actor = match ($role) {
        'owner' => User::factory()->owner()->create(),
        default => User::factory()->create(),
    };
    $subject = in_array($ability, ['viewAny', 'create']) ? $model : $model::factory()->create();

    expect(Gate::forUser($actor)->allows($ability, $subject))
        ->toBe(in_array($ability, ['viewAny', 'view']) || $role === 'owner');
})->with(['employee', 'owner'])->with(['client' => [Client::class], 'person' => [AuthorizedPerson::class]])
    ->with(['viewAny', 'view', 'create', 'update', 'delete']);

it('labels each user type in Spanish', function (string $role, string $label) {
    $user = match ($role) {
        'owner' => User::factory()->owner()->make(),
        default => User::factory()->make(),
    };

    expect($user->typeLabel())->toBe($label);
})->with([['employee', 'Empleado'], ['owner', 'Administradora']]);
