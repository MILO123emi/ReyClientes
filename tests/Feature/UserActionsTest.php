<?php

use App\Actions\Users\CreateUser;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

it('normalizes emails and assigns protected flags through the action', function () {
    $user = app(CreateUser::class)->handle('Usuario de Prueba', ' Manager@Example.com ', 'abcdefgh', false, true, true);

    expect($user->email)->toBe('manager@example.com')
        ->and($user->canManageCards())->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and(Hash::check('abcdefgh', $user->password))->toBeTrue();
});

it('rejects a second principal in the action before inserting', function () {
    User::factory()->owner()->create();

    expect(fn () => app(CreateUser::class)->handle('Otra Principal de Prueba', 'second@example.com', 'abcdefgh', true, false, false))
        ->toThrow(DomainException::class, 'Ya existe una administradora principal.');
    $this->assertDatabaseCount('users', 1);
});

it('rejects a second principal at the database boundary', function () {
    User::factory()->owner()->create();

    expect(fn () => User::factory()->owner()->create())->toThrow(QueryException::class);
    $this->assertDatabaseCount('users', 1);
});

it('does not mass assign protected role flags', function () {
    $user = User::query()->create([
        'name' => 'Usuario de Prueba', 'email' => 'user@example.com', 'password' => 'abcdefgh',
        'is_owner' => true, 'can_manage_cards' => true, 'must_change_password' => true,
    ])->refresh();

    expect($user->isOwner())->toBeFalse()->and($user->canManageCards())->toBeFalse()
        ->and($user->must_change_password)->toBeFalse();
});

it('records the last successful login', function () {
    $this->travelTo(now()->startOfSecond());
    $user = User::factory()->create();
    expect($user->last_login_at)->toBeNull();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->last_login_at?->equalTo(now()))->toBeTrue();
});

it('does not update the last login after an unsuccessful login', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrecta'])
        ->assertSessionHasErrors('email');

    expect($user->refresh()->last_login_at)->toBeNull();
});

it('seeds exactly the three fictional local accounts without forcing a password change', function () {
    $this->app->detectEnvironment(fn () => 'local');

    $this->seed();
    $this->seed();

    $this->assertDatabaseCount('users', 3);
    $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'is_owner' => true, 'must_change_password' => false]);
    $this->assertDatabaseHas('users', ['email' => 'gestion@example.com', 'can_manage_cards' => true, 'must_change_password' => false]);
    $this->assertDatabaseHas('users', ['email' => 'consulta@example.com', 'is_owner' => false, 'can_manage_cards' => false, 'must_change_password' => false]);
    foreach (User::all() as $user) {
        expect(Hash::check('password', $user->password))->toBeTrue();
    }
});

it('never seeds default users outside the local environment', function (string $environment) {
    $this->app->detectEnvironment(fn () => $environment);

    $this->seed();

    $this->assertDatabaseCount('users', 0);
})->with(['testing', 'staging', 'production']);
