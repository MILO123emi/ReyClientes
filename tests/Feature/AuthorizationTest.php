<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'can:manage-cards'])->post('/_tests/cards', fn () => response('OK'));
    Route::middleware(['web', 'auth', 'can:viewAny,'.User::class])->get('/_tests/users', fn () => response('OK'));
    Route::middleware(['web', 'auth', 'can:view-audit'])->get('/_tests/audit', fn () => response('OK'));
});

dataset('roles', [
    'consulta' => ['reader', false, false],
    'gestión' => ['manager', true, false],
    'principal' => ['owner', true, true],
]);

it('enforces permissions at the HTTP boundary', function (string $role, bool $cards, bool $administration) {
    $user = match ($role) {
        'owner' => User::factory()->owner()->create(),
        'manager' => User::factory()->manager()->create(),
        default => User::factory()->create(),
    };

    $this->actingAs($user)->post('/_tests/cards')->assertStatus($cards ? 200 : 403);
    $this->get('/_tests/users')->assertStatus($administration ? 200 : 403);
    $this->get('/_tests/audit')->assertStatus($administration ? 200 : 403);
})->with('roles');

it('shares only the required identity and permissions for each role', function (string $role, bool $cards, bool $administration) {
    $user = match ($role) {
        'owner' => User::factory()->owner()->create(),
        'manager' => User::factory()->manager()->create(),
        default => User::factory()->create(),
    };

    $this->actingAs($user)->get(route('clients.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'can' => ['manageCards' => $cards, 'manageUsers' => $administration, 'viewAudit' => $administration],
        ]));
})->with('roles');

it('applies permission revocation on the next request in the same session', function () {
    $user = User::factory()->manager()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post('/_tests/cards')->assertOk();
    User::query()->whereKey($user->id)->update(['can_manage_cards' => false]);
    $this->app['auth']->forgetGuards();

    $this->post('/_tests/cards')->assertForbidden();
    $this->assertAuthenticatedAs($user);
});

it('requires authentication for every permission boundary', function (string $method, string $uri) {
    $this->call($method, $uri)->assertRedirect(route('login'));
})->with([['POST', '/_tests/cards'], ['GET', '/_tests/users'], ['GET', '/_tests/audit']]);

it('allows only the principal to update or delete ordinary users', function (string $role, bool $cards, bool $administration) {
    $actor = match ($role) {
        'owner' => User::factory()->owner()->create(),
        'manager' => User::factory()->manager()->create(),
        default => User::factory()->create(),
    };
    $subject = User::factory()->create();
    $principal = $actor->isOwner() ? $actor : User::factory()->owner()->create();

    expect(Gate::forUser($actor)->allows('create', User::class))->toBe($administration)
        ->and(Gate::forUser($actor)->allows('update', $subject))->toBe($administration)
        ->and(Gate::forUser($actor)->allows('delete', $subject))->toBe($administration)
        ->and(Gate::forUser($actor)->allows('update', $principal))->toBeFalse()
        ->and(Gate::forUser($actor)->allows('delete', $principal))->toBeFalse();
})->with('roles');
