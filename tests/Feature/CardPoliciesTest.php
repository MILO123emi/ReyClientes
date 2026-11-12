<?php

use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('enforces card permissions for every role model and operation', function (string $role, string $model, string $ability) {
    $actor = match ($role) {
        'owner' => User::factory()->owner()->create(),
        'manager' => User::factory()->manager()->create(),
        default => User::factory()->create(),
    };
    $subject = in_array($ability, ['viewAny', 'create']) ? $model : $model::factory()->create();

    expect(Gate::forUser($actor)->allows($ability, $subject))
        ->toBe(in_array($ability, ['viewAny', 'view']) || $role !== 'reader');
})->with(['reader', 'manager', 'owner'])->with(['client' => [Client::class], 'person' => [AuthorizedPerson::class]])
    ->with(['viewAny', 'view', 'create', 'update', 'delete']);

it('labels each user type in Spanish', function (string $role, string $label) {
    $user = match ($role) {
        'owner' => User::factory()->owner()->make(),
        'manager' => User::factory()->manager()->make(),
        default => User::factory()->make(),
    };

    expect($user->typeLabel())->toBe($label);
})->with([['reader', 'Consulta'], ['manager', 'Gestión'], ['owner', 'Principal']]);
