<?php

use App\Enums\AuditAction;
use App\Mail\EmailChangeCode;
use App\Models\AuditLog;
use App\Models\EmailChangeRequest;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/** @param array<string, mixed> $overrides */
function pendingEmailChange(User $user, string $email = 'nuevo@example.com', array $overrides = []): EmailChangeRequest
{
    $pending = new EmailChangeRequest;
    $pending->forceFill(array_replace([
        'user_id' => $user->id, 'new_email' => $email, 'code_hash' => Hash::make('123456'),
        'expires_at' => now()->addMinutes(10), 'attempts' => 0,
    ], $overrides))->save();

    return $pending;
}

it('keeps the email pages and actions away from guests and employees', function (string $method, string $route) {
    $this->{$method}(route($route))->assertRedirect(route('login'));

    $employee = User::factory()->create();
    $this->actingAs($employee)->{$method}(route($route), ['email' => 'otro@example.com', 'code' => '123456', 'current_password' => 'password'])
        ->assertForbidden();

    expect($employee->refresh()->email)->not->toBe('otro@example.com');
    expect(EmailChangeRequest::query()->count())->toBe(0);
})->with([
    ['get', 'account.email'], ['post', 'account.email.store'], ['post', 'account.email.confirm'], ['delete', 'account.email.destroy'],
]);

it('shows the principal her current email and shares the permission', function () {
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);

    $this->actingAs($owner)->get(route('account.email'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('account/Email')
            ->where('email', 'owner@example.com')->where('pending', null)
            ->where('auth.user.can.changeEmail', true));

    $this->actingAs(User::factory()->create())->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.can.changeEmail', false));
});

it('requires the current password before sending anything', function (array $payload, string $error) {
    Mail::fake();
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);

    $this->actingAs($owner)->post(route('account.email.store'), ['email' => 'nuevo@example.com', ...$payload])
        ->assertSessionHasErrors(['current_password' => $error]);

    Mail::assertNothingSent();
    expect(EmailChangeRequest::query()->count())->toBe(0);
    expect($owner->refresh()->email)->toBe('owner@example.com');
})->with([
    'missing' => [[], 'Escribe tu contraseña para confirmar.'],
    'wrong' => [['current_password' => 'wrong-password'], 'La contraseña es incorrecta.'],
]);

it('rejects invalid or already used addresses', function (string $email) {
    Mail::fake();
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
    User::factory()->create(['email' => 'empleado@example.com']);

    $this->actingAs($owner)->post(route('account.email.store'), ['email' => $email, 'current_password' => 'password'])
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
    expect(EmailChangeRequest::query()->count())->toBe(0);
})->with(['no-es-correo', 'Owner@Example.com', ' EMPLEADO@example.com ', '']);

it('sends a hashed six digit code to the new address without changing the email yet', function () {
    Mail::fake();
    $this->travelTo(now()->startOfSecond());
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
    pendingEmailChange($owner, 'viejo-intento@example.com');

    $this->actingAs($owner)->post(route('account.email.store'), ['email' => ' Nuevo@Example.com ', 'current_password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect(route('account.email'));

    Mail::assertSent(EmailChangeCode::class, fn (EmailChangeCode $mail): bool => $mail->hasTo('nuevo@example.com') && ! $mail->hasTo('owner@example.com'));
    $code = Mail::sent(EmailChangeCode::class)->sole()->code;
    $pending = EmailChangeRequest::query()->sole();

    expect($code)->toMatch('/^\d{6}$/');
    expect($pending->new_email)->toBe('nuevo@example.com');
    expect($pending->getRawOriginal('code_hash'))->not->toContain($code);
    expect(Hash::check($code, $pending->code_hash))->toBeTrue();
    expect($pending->expires_at->equalTo(now()->addMinutes(10)))->toBeTrue();
    expect($owner->refresh()->email)->toBe('owner@example.com');

    $audit = AuditLog::query()->where('action', AuditAction::UserEmailChangeRequested->value)->sole();
    expect($audit->user_id)->toBe($owner->id);
    expect($audit->description)->toContain('nuevo@example.com')->not->toContain($code);

    $this->actingAs($owner)->get(route('account.email'))
        ->assertInertia(fn (Assert $page) => $page->where('pending.email', 'nuevo@example.com')
            ->where('pending.expiresAt', now()->addMinutes(10)->toIso8601String()));
});

it('leaves no pending change when the message cannot be sent', function () {
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp down'));

    $this->actingAs($owner)->post(route('account.email.store'), ['email' => 'nuevo@example.com', 'current_password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(EmailChangeRequest::query()->count())->toBe(0);
    expect(AuditLog::query()->where('action', AuditAction::UserEmailChangeRequested->value)->count())->toBe(0);
});

it('refuses the log transport outside local', function () {
    config(['mail.default' => 'log']);
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('account.email.store'), ['email' => 'nuevo@example.com', 'current_password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(EmailChangeRequest::query()->count())->toBe(0);
});

it('applies the new email only with the right code and keeps just the current session', function () {
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
    pendingEmailChange($owner);
    (new PasswordResetCode)->forceFill(['user_id' => $owner->id, 'code_hash' => Hash::make('654321'), 'expires_at' => now()->addMinutes(10)])->save();
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($owner)->post(route('account.email.confirm'), ['code' => '123 456'])
        ->assertSessionHasNoErrors()->assertRedirect(route('account.email'));

    expect($owner->refresh()->email)->toBe('nuevo@example.com');
    expect(EmailChangeRequest::query()->count())->toBe(0);
    expect(PasswordResetCode::query()->count())->toBe(0);
    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse();
    $this->assertAuthenticatedAs($owner);

    $audit = AuditLog::query()->where('action', AuditAction::UserEmailChanged->value)->sole();
    expect($audit->description)->toContain('owner@example.com', 'nuevo@example.com');

    $this->post(route('account.email.confirm'), ['code' => '123456'])->assertSessionHasErrors('code');
});

it('lets her sign in with the new address and no longer with the old one', function () {
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
    pendingEmailChange($owner);
    $this->actingAs($owner)->post(route('account.email.confirm'), ['code' => '123456'])->assertSessionHasNoErrors();
    $this->post(route('logout'));

    $this->post(route('login.store'), ['email' => 'owner@example.com', 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();
    $this->post(route('login.store'), ['email' => 'nuevo@example.com', 'password' => 'password'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($owner);
});

it('keeps the email after a wrong code and invalidates the code at the fifth failure', function () {
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
    $pending = pendingEmailChange($owner);

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($owner)->post(route('account.email.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors(['code' => 'El código no es correcto.']);
        expect($pending->refresh()->attempts)->toBe($attempt);
    }

    $this->actingAs($owner)->post(route('account.email.confirm'), ['code' => '123456'])
        ->assertSessionHasErrors(['code' => 'El código venció o ya no es válido. Pide uno nuevo.']);

    expect($owner->refresh()->email)->toBe('owner@example.com');
    expect(AuditLog::query()->where('action', AuditAction::UserEmailChangeFailed->value)->count())->toBe(5);
    $this->actingAs($owner)->get(route('account.email'))->assertInertia(fn (Assert $page) => $page->where('pending', null));
});

it('rejects an expired code', function () {
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
    pendingEmailChange($owner);
    $this->travel(11)->minutes();

    $this->actingAs($owner)->post(route('account.email.confirm'), ['code' => '123456'])->assertSessionHasErrors('code');

    expect($owner->refresh()->email)->toBe('owner@example.com');
});

it('does not take an address another account claimed while the code was pending', function () {
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
    pendingEmailChange($owner);
    User::factory()->create(['email' => 'nuevo@example.com']);

    $this->actingAs($owner)->post(route('account.email.confirm'), ['code' => '123456'])
        ->assertSessionHasErrors(['code' => 'Ese correo ya lo usa otra cuenta.']);

    expect($owner->refresh()->email)->toBe('owner@example.com');
    expect(EmailChangeRequest::query()->count())->toBe(0);
});

it('cancels a pending change', function () {
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
    pendingEmailChange($owner);

    $this->actingAs($owner)->delete(route('account.email.destroy'))->assertRedirect(route('account.email'));

    expect(EmailChangeRequest::query()->count())->toBe(0);
    expect($owner->refresh()->email)->toBe('owner@example.com');
});

it('limits how many codes can be requested and verified', function () {
    Mail::fake();
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);

    foreach (range(1, 3) as $attempt) {
        $this->actingAs($owner)->post(route('account.email.store'), ['email' => "nuevo{$attempt}@example.com", 'current_password' => 'password'])
            ->assertRedirect(route('account.email'));
    }
    $this->actingAs($owner)->post(route('account.email.store'), ['email' => 'nuevo4@example.com', 'current_password' => 'password'])
        ->assertTooManyRequests();
    Mail::assertSentCount(3);

    foreach (range(1, 10) as $attempt) {
        $this->actingAs($owner)->post(route('account.email.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
    }
    $this->actingAs($owner)->post(route('account.email.confirm'), ['code' => '000000'])->assertTooManyRequests();
});

it('prunes stale pending changes', function () {
    $owner = User::factory()->owner()->create();
    pendingEmailChange($owner, overrides: ['expires_at' => now()->subDays(2)]);

    $this->artisan('model:prune')->assertSuccessful();

    expect(EmailChangeRequest::query()->count())->toBe(0);
});

it('changes an email from the server console and closes every session', function () {
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
    pendingEmailChange($owner, 'otro@example.com');
    DB::table('sessions')->insert(['id' => 'device', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);

    $this->artisan('admin:set-email', ['--owner' => true, 'email' => ' Nuevo@Example.com '])
        ->expectsConfirmation('¿Cambiar el correo de '.$owner->name.' de owner@example.com a nuevo@example.com?', 'yes')
        ->assertSuccessful();

    expect($owner->refresh()->email)->toBe('nuevo@example.com');
    expect(DB::table('sessions')->where('user_id', $owner->id)->exists())->toBeFalse();
    expect(EmailChangeRequest::query()->count())->toBe(0);

    $audit = AuditLog::query()->where('action', AuditAction::UserEmailChanged->value)->sole();
    expect($audit->user_id)->toBeNull();
    expect($audit->description)->toContain('desde la consola del servidor');
});

it('refuses console changes to a missing user, an invalid address or a used address', function (array $arguments) {
    User::factory()->owner()->create(['email' => 'owner@example.com']);
    User::factory()->create(['email' => 'empleado@example.com']);

    $command = $this->artisan('admin:set-email', $arguments);
    if (($arguments['new-email'] ?? '') === 'owner@example.com') {
        $command->expectsConfirmation('¿Cambiar el correo de '.User::query()->where('email', 'empleado@example.com')->sole()->name.' de empleado@example.com a owner@example.com?', 'yes');
    }
    $command->assertFailed();

    expect(User::query()->orderBy('id')->pluck('email')->all())->toBe(['owner@example.com', 'empleado@example.com']);
})->with([
    'missing user' => [['email' => 'nadie@example.com', 'new-email' => 'nuevo@example.com']],
    'invalid address' => [['email' => 'empleado@example.com', 'new-email' => 'no-es-correo']],
    'used address' => [['email' => 'empleado@example.com', 'new-email' => 'owner@example.com']],
]);
