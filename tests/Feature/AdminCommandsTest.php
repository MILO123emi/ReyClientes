<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('creates exactly one principal through the console', function () {
    $this->artisan('admin:create', ['--owner' => true])
        ->expectsQuestion('Nombre', 'Administradora de Prueba')
        ->expectsQuestion('Correo electrónico', 'owner@example.com')
        ->expectsQuestion('Contraseña', 'abcdefgh')
        ->expectsQuestion('Confirma la contraseña', 'abcdefgh')
        ->doesntExpectOutputToContain('abcdefgh')
        ->expectsOutput('Administradora principal creada.')
        ->assertSuccessful();

    $user = User::query()->sole();
    expect($user->isOwner())->toBeTrue()->and($user->must_change_password)->toBeFalse()
        ->and(Hash::check('abcdefgh', $user->password))->toBeTrue();

    $this->artisan('admin:create', ['--owner' => true])
        ->expectsOutput('Ya existe una administradora principal.')->assertFailed();
    $this->assertDatabaseCount('users', 1);
});

it('creates ordinary users with the chosen permission and a temporary password', function (bool $permission) {
    $this->artisan('admin:create')
        ->expectsQuestion('Nombre', 'Usuario de Prueba')
        ->expectsQuestion('Correo electrónico', 'user@example.com')
        ->expectsQuestion('Contraseña', 'abcdefgh')
        ->expectsQuestion('Confirma la contraseña', 'abcdefgh')
        ->expectsConfirmation('¿Puede agregar, editar y borrar clientes?', $permission ? 'yes' : 'no')
        ->doesntExpectOutputToContain('abcdefgh')
        ->assertSuccessful();

    $user = User::query()->sole();
    expect($user->isOwner())->toBeFalse()
        ->and($user->can_manage_cards)->toBe($permission)
        ->and($user->must_change_password)->toBeTrue()
        ->and(Hash::check('abcdefgh', $user->password))->toBeTrue();
})->with([true, false]);

it('normalizes the email before creation, login and password reset', function () {
    $this->artisan('admin:create', ['--owner' => true])
        ->expectsQuestion('Nombre', 'Ana de Prueba')
        ->expectsQuestion('Correo electrónico', ' Ana@Example.com ')
        ->expectsQuestion('Contraseña', 'abcdefgh')
        ->expectsQuestion('Confirma la contraseña', 'abcdefgh')
        ->assertSuccessful();
    $user = User::query()->sole();
    expect($user->email)->toBe('ana@example.com');

    $this->post(route('login.store'), ['email' => 'Ana@Example.com', 'password' => 'abcdefgh'])
        ->assertSessionHasNoErrors()->assertRedirect(route('clients.index', absolute: false));
    $this->assertAuthenticatedAs($user);

    $this->artisan('admin:reset-password', ['email' => 'Ana@Example.com'])
        ->expectsQuestion('Nueva contraseña', 'ijklmnop')
        ->expectsQuestion('Confirma la contraseña', 'ijklmnop')
        ->doesntExpectOutputToContain('ijklmnop')
        ->assertSuccessful();
    expect(Hash::check('ijklmnop', $user->refresh()->password))->toBeTrue();
});

it('rejects a case-insensitive duplicate email before creating a user', function () {
    User::factory()->create(['email' => 'ana@example.com']);

    $this->artisan('admin:create')
        ->expectsQuestion('Nombre', 'Ana de Prueba')
        ->expectsQuestion('Correo electrónico', 'Ana@Example.com')
        ->expectsQuestion('Contraseña', 'abcdefgh')
        ->expectsQuestion('Confirma la contraseña', 'abcdefgh')
        ->assertFailed();

    $this->assertDatabaseCount('users', 1);
});

it('rejects invalid creation data without persisting users', function (array $overrides) {
    $input = array_replace(['name' => 'Usuario de Prueba', 'email' => 'user@example.com', 'password' => 'abcdefgh', 'confirmation' => 'abcdefgh'], $overrides);

    $this->artisan('admin:create')
        ->expectsQuestion('Nombre', $input['name'])
        ->expectsQuestion('Correo electrónico', $input['email'])
        ->expectsQuestion('Contraseña', $input['password'])
        ->expectsQuestion('Confirma la contraseña', $input['confirmation'])
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
})->with([
    'nombre vacío' => [['name' => '']],
    'nombre demasiado largo' => [['name' => str_repeat('A', 121)]],
    'correo inválido' => [['email' => 'inválido']],
    'contraseña corta' => [['password' => '1234567', 'confirmation' => '1234567']],
    'confirmación distinta' => [['confirmation' => 'diferente']],
]);

it('resets passwords and closes only the affected users sessions', function (bool $owner) {
    $user = $owner ? User::factory()->owner()->create() : User::factory()->create();
    $other = User::factory()->create();
    foreach (['target-one', 'target-two', 'other'] as $id) {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $id === 'other' ? $other->id : $user->id,
            'payload' => '', 'last_activity' => time(),
        ]);
    }

    $this->artisan('admin:reset-password', ['email' => $user->email])
        ->expectsQuestion('Nueva contraseña', 'abcdefgh')
        ->expectsQuestion('Confirma la contraseña', 'abcdefgh')
        ->doesntExpectOutputToContain('abcdefgh')
        ->assertSuccessful();

    expect(Hash::check('abcdefgh', $user->refresh()->password))->toBeTrue()
        ->and($user->must_change_password)->toBe(! $owner);
    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertDatabaseHas('sessions', ['id' => 'other', 'user_id' => $other->id]);
})->with([true, false]);

it('does not reset a password when confirmation fails', function () {
    $user = User::factory()->create();
    $hash = $user->password;
    DB::table('sessions')->insert(['id' => 'kept', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->artisan('admin:reset-password', ['email' => $user->email])
        ->expectsQuestion('Nueva contraseña', 'abcdefgh')
        ->expectsQuestion('Confirma la contraseña', 'different')
        ->assertFailed();

    expect($user->refresh()->password)->toBe($hash);
    $this->assertDatabaseHas('sessions', ['id' => 'kept']);
});

it('reports a missing user without asking for secrets', function () {
    $this->artisan('admin:reset-password', ['email' => 'missing@example.com'])
        ->expectsOutput('No existe un usuario con ese correo electrónico.')->assertFailed();
});
