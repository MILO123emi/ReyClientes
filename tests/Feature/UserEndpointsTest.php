<?php

use App\Actions\Audit\RecordAudit;
use App\Actions\Users\DeleteUser;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('shows only the managed user resource fields to the principal', function () {
    $this->travelTo(now()->startOfSecond());
    $owner = User::factory()->owner()->create();
    $user = User::factory()->create(['last_login_at' => now()]);

    $response = $this->actingAs($owner)->get(route('users.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('users/Index')->has('users.data', 2)
            ->where('users.data.0.id', $owner->id)->where('users.data.0.type', 'Administradora')
            ->where('users.data.1.last_login_at', $user->last_login_at->toIso8601String()));

    expect($response->inertiaProps('users.data.1'))->toHaveKeys([
        'id', 'name', 'email', 'type', 'is_owner', 'last_login_at',
    ])->toHaveCount(6);
    expect(json_encode($response->inertiaProps('users')))->not->toContain($user->password, 'remember_token', 'must_change_password');
});

it('creates normalized managed users with temporary passwords and ignores obsolete permission input', function (bool $permission) {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->from(route('users.index'))->post(route('users.store'), [
        'name' => '  Usuario De Prueba  ', 'email' => ' NewUser@Example.com ',
        'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh', implode('_', ['can', 'manage', 'cards']) => $permission,
        'current_password' => 'password', 'is_owner' => true, 'must_change_password' => false,
    ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    $user = User::query()->where('email', 'newuser@example.com')->sole();
    expect($user->name)->toBe('Usuario De Prueba');
    expect($user->is_owner)->toBeFalse();
    expect($user->isOwner())->toBeFalse();
    expect($user->must_change_password)->toBeTrue();
    expect(Hash::check('abcdefgh', $user->password))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.created', 'user_id' => $owner->id, 'subject_type' => 'user', 'subject_id' => $user->id,
    ]);
})->with([false, true]);

it('creates an employee when permission input is omitted', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('users.store'), [
        'name' => 'Usuario De Prueba', 'email' => 'newuser@example.com',
        'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh', 'current_password' => 'password',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'newuser@example.com')->sole()->is_owner)->toBeFalse();
});

it('rejects invalid managed user input without creating users or audit', function (string $kind, string $field) {
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
    $payload = [
        'name' => 'Usuario De Prueba', 'email' => 'newuser@example.com',
        'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh', 'current_password' => 'password',
    ];
    $overrides = match ($kind) {
        'name-missing' => ['name' => ''],
        'name-long' => ['name' => str_repeat('a', 121)],
        'email-invalid' => ['email' => 'correo inválido'],
        'email-duplicate' => ['email' => ' Owner@Example.com '],
        'password-short' => ['password' => '1234567', 'password_confirmation' => '1234567'],
        'confirmation' => ['password_confirmation' => 'different'],
    };

    $this->actingAs($owner)->post(route('users.store'), array_replace($payload, $overrides))->assertSessionHasErrors($field);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('audit_logs', 0);
})->with([
    ['name-missing', 'name'], ['name-long', 'name'], ['email-invalid', 'email'], ['email-duplicate', 'email'],
    ['password-short', 'password'], ['confirmation', 'password'],
]);

it('assigns a temporary password and closes only the affected users sessions', function () {
    $owner = User::factory()->owner()->create();
    $user = User::factory()->create();
    $rememberToken = $user->remember_token;
    foreach (['target-one', 'target-two', 'owner-session'] as $id) {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $id === 'owner-session' ? $owner->id : $user->id,
            'payload' => '', 'last_activity' => time(),
        ]);
    }

    $this->actingAs($owner)->put(route('users.password', $user), [
        'password' => 'temporary-password', 'password_confirmation' => 'temporary-password', 'current_password' => 'password',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('temporary-password', $user->refresh()->password))->toBeTrue();
    expect($user->must_change_password)->toBeTrue();
    expect($user->remember_token)->not->toBe($rememberToken);
    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertDatabaseHas('sessions', ['id' => 'owner-session']);
    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('user.password_set_by_owner');
    expect($log->description)->not->toContain('temporary-password');
});

it('protects the principal from permission password and deletion requests', function (string $operation) {
    $owner = User::factory()->owner()->create();
    $originalPassword = $owner->password;
    $method = $operation === 'delete' ? 'DELETE' : 'PUT';
    $route = match ($operation) {
        'password' => 'users.password', 'delete' => 'users.destroy',
    };

    $this->actingAs($owner)->call($method, route($route, $owner), [
        'current_password' => 'password',
        'password' => 'temporary-password', 'password_confirmation' => 'temporary-password',
    ])->assertForbidden();

    $this->assertModelExists($owner);
    expect($owner->refresh()->is_owner)->toBeTrue();
    expect($owner->password)->toBe($originalPassword);
    $this->assertDatabaseCount('audit_logs', 0);
})->with(['password', 'delete']);

it('deletes ordinary users and their sessions while retaining existing audit records', function () {
    $owner = User::factory()->owner()->create();
    $user = User::factory()->create(['name' => 'Usuario De Prueba', 'email' => 'user@example.com']);
    $previous = app(RecordAudit::class)->handle(AuditAction::AuthLogin, 'Inicio de sesión de prueba', actor: $user);
    DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($owner)->delete(route('users.destroy', $user), ['current_password' => 'password'])->assertSessionHasNoErrors();

    $this->assertModelMissing($user);
    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    expect($previous->refresh()->user_id)->toBeNull();
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.deleted', 'user_id' => $owner->id,
        'description' => 'Usuario Usuario De Prueba (user@example.com) eliminado',
    ]);
});

it('also prevents principal deletion at the action boundary', function () {
    $owner = User::factory()->owner()->create();

    expect(fn () => app(DeleteUser::class)->handle($owner))->toThrow(DomainException::class);

    $this->assertModelExists($owner);
    $this->assertDatabaseCount('audit_logs', 0);
});
