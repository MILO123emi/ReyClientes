<?php

use App\Mail\PasswordResetCode as PasswordResetCodeMail;
use App\Mail\TestMail;
use App\Models\AuditLog;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 */
function recoveryCodeFor(User $user, array $overrides = []): PasswordResetCode
{
    $reset = new PasswordResetCode;
    $reset->forceFill(array_replace([
        'user_id' => $user->id, 'code_hash' => Hash::make('123456'),
        'expires_at' => now()->addMinutes(10), 'attempts' => 0, 'used_at' => null,
    ], $overrides))->save();

    return $reset;
}

/** @return array<string, mixed> */
function recoveryPasswordPayload(string $code = '123456'): array
{
    return ['code' => $code, 'password' => 'new-password', 'password_confirmation' => 'new-password'];
}

it('renders the recovery request page and redirects verification without recovery session data', function () {
    $this->get(route('recovery.request'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/RecoverRequest'));
    $this->get(route('recovery.verify'))->assertRedirect(route('recovery.request'));
});

it('sends only the principal a six digit code stored as a hash expiring in ten minutes', function () {
    Mail::fake();
    $this->travelTo(now()->startOfSecond());
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);

    $response = $this->post(route('recovery.send'), ['email' => ' Owner@Example.com '])
        ->assertRedirect(route('recovery.verify'))->assertSessionHas('recovery.email', 'owner@example.com')
        ->assertSessionHas('recovery.sent_at', now()->toIso8601String());

    Mail::assertSent(PasswordResetCodeMail::class, fn (PasswordResetCodeMail $mail): bool => $mail->hasTo($owner->email));
    $mail = Mail::sent(PasswordResetCodeMail::class)->sole();
    expect($mail->code)->toMatch('/^\d{6}$/');
    $reset = PasswordResetCode::query()->sole();
    expect($reset->user_id)->toBe($owner->id);
    expect($reset->code_hash)->not->toBe($mail->code);
    expect(Hash::check($mail->code, $reset->code_hash))->toBeTrue();
    expect($reset->expires_at->equalTo(now()->addMinutes(10)))->toBeTrue();
    expect($reset->attempts)->toBe(0);
    expect($reset->used_at)->toBeNull();
    expect($reset->toArray())->not->toHaveKey('code_hash');
    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('auth.reset_code_sent');
    expect($log->user_id)->toBeNull();
    expect($log->description)->not->toContain($mail->code);
    expect($response->headers->get('Location'))->not->toContain($owner->email, $mail->code);
});

it('returns the same recovery response for ordinary and nonexistent accounts without sending mail', function (string $email) {
    Mail::fake();
    User::factory()->owner()->create(['email' => 'owner@example.com']);
    User::factory()->create(['email' => 'user@example.com']);

    $this->post(route('recovery.send'), ['email' => $email])->assertRedirect(route('recovery.verify'))
        ->assertSessionHas('status', 'Si el correo corresponde a la cuenta principal, te enviamos un código de 6 dígitos. Si eres otro usuario, pídele a la administradora que cambie tu contraseña.');

    Mail::assertNothingSent();
    $this->assertDatabaseCount('password_reset_codes', 0);
    $this->assertDatabaseCount('audit_logs', 0);
})->with(['user@example.com', 'missing@example.com']);

it('shows verification status and sent time without exposing the email or a reset code in props', function () {
    Mail::fake();
    $this->travelTo(now()->startOfSecond());
    User::factory()->owner()->create(['email' => 'owner@example.com']);
    $this->post(route('recovery.send'), ['email' => 'owner@example.com'])->assertRedirect();
    $code = Mail::sent(PasswordResetCodeMail::class)->sole()->code;

    $response = $this->get(route('recovery.verify'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/RecoverVerify')
            ->where('sentAt', now()->toIso8601String())->has('passwordRules'));

    expect(json_encode($response->inertiaProps()))->not->toContain('owner@example.com', $code);
});

it('replaces previous codes and can resend using the session email', function () {
    Mail::fake();
    $this->travelTo(now()->startOfSecond());
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
    $old = recoveryCodeFor($owner);

    $this->post(route('recovery.send'), ['email' => $owner->email])->assertRedirect(route('recovery.verify'));
    $first = PasswordResetCode::query()->sole();
    $this->post(route('recovery.send'))->assertRedirect(route('recovery.verify'));

    $this->assertModelMissing($old);
    $this->assertModelMissing($first);
    $this->assertDatabaseCount('password_reset_codes', 1);
    Mail::assertSentCount(2);
    expect(AuditLog::query()->where('action', 'auth.reset_code_sent')->count())->toBe(2);
});

it('recovers the password once clears temporary state and invalidates all remembered and database sessions', function () {
    Mail::fake();
    $this->travelTo(now()->startOfSecond());
    $owner = User::factory()->owner()->mustChangePassword()->create();
    $other = User::factory()->create();
    $rememberToken = $owner->remember_token;
    $reset = recoveryCodeFor($owner);
    foreach (['owner-one', 'owner-two', 'other-user'] as $id) {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $id === 'other-user' ? $other->id : $owner->id,
            'payload' => '', 'last_activity' => time(),
        ]);
    }

    $this->withSession(['recovery' => ['email' => $owner->email]])
        ->post(route('recovery.verify.store'), recoveryPasswordPayload())->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Tu contraseña se cambió. Inicia sesión.')
        ->assertSessionMissing('recovery');

    expect(Hash::check('new-password', $owner->refresh()->password))->toBeTrue();
    expect($owner->must_change_password)->toBeFalse();
    expect($owner->remember_token)->not->toBe($rememberToken);
    expect($reset->refresh()->used_at?->equalTo(now()))->toBeTrue();
    $this->assertDatabaseMissing('sessions', ['user_id' => $owner->id]);
    $this->assertDatabaseHas('sessions', ['id' => 'other-user']);
    $this->assertGuest();
    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('auth.password_reset');
    expect($log->description)->not->toContain('123456', 'new-password');
    $passwordHash = $owner->password;

    $this->withSession(['recovery' => ['email' => $owner->email]])
        ->post(route('recovery.verify.store'), recoveryPasswordPayload())
        ->assertSessionHasErrors(['code' => 'El código venció o ya no es válido. Pide uno nuevo.']);

    expect($owner->refresh()->password)->toBe($passwordHash);
    $this->assertDatabaseCount('audit_logs', 1);
});

it('counts incorrect codes without changing the password and audits no secrets', function () {
    Mail::fake();
    $owner = User::factory()->owner()->create();
    $hash = $owner->password;
    $reset = recoveryCodeFor($owner);

    $this->withSession(['recovery' => ['email' => $owner->email]])
        ->post(route('recovery.verify.store'), recoveryPasswordPayload('654321'))
        ->assertSessionHasErrors(['code' => 'El código no es correcto.'])
        ->assertSessionMissing('_old_input.code')->assertSessionMissing('_old_input.password');

    expect($reset->refresh()->attempts)->toBe(1);
    expect($reset->used_at)->toBeNull();
    expect($owner->refresh()->password)->toBe($hash);
    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('auth.reset_code_failed');
    expect($log->description)->not->toContain('123456', '654321', 'new-password');
});

it('invalidates a recovery code after five incorrect attempts even when the next code is correct', function () {
    Mail::fake();
    $owner = User::factory()->owner()->create();
    $hash = $owner->password;
    $reset = recoveryCodeFor($owner);
    $this->withSession(['recovery' => ['email' => $owner->email]]);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('recovery.verify.store'), recoveryPasswordPayload('654321'))->assertSessionHasErrors('code');
    }

    $this->post(route('recovery.verify.store'), recoveryPasswordPayload())
        ->assertSessionHasErrors(['code' => 'El código venció o ya no es válido. Pide uno nuevo.']);

    expect($reset->refresh()->attempts)->toBe(5);
    expect($owner->refresh()->password)->toBe($hash);
    expect(AuditLog::query()->where('action', 'auth.reset_code_failed')->count())->toBe(5);
});

it('rejects expired used missing and exhausted codes without changing password state', function (string $kind) {
    Mail::fake();
    $this->travelTo(now()->startOfSecond());
    $owner = User::factory()->owner()->create();
    $hash = $owner->password;
    if ($kind !== 'missing') {
        recoveryCodeFor($owner, match ($kind) {
            'expired' => ['expires_at' => now()->subSecond()],
            'expiry-boundary' => ['expires_at' => now()],
            'used' => ['used_at' => now()],
            'exhausted' => ['attempts' => 5],
        });
    }

    $this->withSession(['recovery' => ['email' => $owner->email]])
        ->post(route('recovery.verify.store'), recoveryPasswordPayload())
        ->assertSessionHasErrors(['code' => 'El código venció o ya no es válido. Pide uno nuevo.']);

    expect($owner->refresh()->password)->toBe($hash);
    $this->assertDatabaseCount('audit_logs', 0);
})->with(['expired', 'expiry-boundary', 'used', 'exhausted', 'missing']);

it('rejects a code that became expired eleven minutes after creation', function () {
    Mail::fake();
    $owner = User::factory()->owner()->create();
    recoveryCodeFor($owner);
    $this->travel(11)->minutes();

    $this->withSession(['recovery' => ['email' => $owner->email]])
        ->post(route('recovery.verify.store'), recoveryPasswordPayload())
        ->assertSessionHasErrors(['code' => 'El código venció o ya no es válido. Pide uno nuevo.']);
});

it('never resets ordinary users even if a reset record exists for them', function () {
    Mail::fake();
    $user = User::factory()->create();
    $hash = $user->password;
    recoveryCodeFor($user);

    $this->withSession(['recovery' => ['email' => $user->email]])
        ->post(route('recovery.verify.store'), recoveryPasswordPayload())
        ->assertSessionHasErrors(['code' => 'El código venció o ya no es válido. Pide uno nuevo.']);

    expect($user->refresh()->password)->toBe($hash);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('validates recovery input without counting code attempts', function (array $overrides, string $field) {
    Mail::fake();
    $owner = User::factory()->owner()->create();
    $reset = recoveryCodeFor($owner);

    $this->withSession(['recovery' => ['email' => $owner->email]])
        ->post(route('recovery.verify.store'), array_replace(recoveryPasswordPayload(), $overrides))
        ->assertSessionHasErrors($field);

    expect($reset->refresh()->attempts)->toBe(0);
    expect($reset->used_at)->toBeNull();
    $this->assertDatabaseCount('audit_logs', 0);
})->with([
    'missing code' => [['code' => ''], 'code'],
    'five digits' => [['code' => '12345'], 'code'],
    'letters' => [['code' => 'abcdef'], 'code'],
    'short password' => [['password' => '1234567', 'password_confirmation' => '1234567'], 'password'],
    'unconfirmed password' => [['password_confirmation' => 'different'], 'password'],
]);

it('throttles code delivery after three requests in fifteen minutes and permits it again after expiry', function () {
    Mail::fake();
    $this->travelTo(now()->startOfSecond());
    User::factory()->owner()->create(['email' => 'owner@example.com']);
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->post(route('recovery.send'), ['email' => 'owner@example.com'])->assertRedirect(route('recovery.verify'));
    }

    $this->post(route('recovery.send'), ['email' => 'owner@example.com'])->assertTooManyRequests();
    Mail::assertSentCount(3);
    $this->travel(901)->seconds();
    $this->post(route('recovery.send'), ['email' => 'owner@example.com'])->assertRedirect(route('recovery.verify'));

    Mail::assertSentCount(4);
});

it('throttles verification after ten requests per IP in fifteen minutes', function () {
    Mail::fake();
    $this->travelTo(now()->startOfSecond());
    $this->withSession(['recovery' => ['email' => 'missing@example.com']]);
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->post(route('recovery.verify.store'), recoveryPasswordPayload())->assertSessionHasErrors('code');
    }

    $this->post(route('recovery.verify.store'), recoveryPasswordPayload())->assertTooManyRequests();
    $this->travel(901)->seconds();
    $this->post(route('recovery.verify.store'), recoveryPasswordPayload())->assertSessionHasErrors('code');
});

it('prunes codes expired more than a day ago while preserving boundary recent and future records', function () {
    $this->travelTo(now()->startOfSecond());
    $owner = User::factory()->owner()->create();
    $old = recoveryCodeFor($owner, ['expires_at' => now()->subDays(2)]);
    $boundary = recoveryCodeFor($owner, ['expires_at' => now()->subDay()]);
    $recent = recoveryCodeFor($owner, ['expires_at' => now()->subHour()]);
    $future = recoveryCodeFor($owner);

    $this->artisan('model:prune', ['--model' => [PasswordResetCode::class]])->assertSuccessful();

    $this->assertModelMissing($old);
    $this->assertModelExists($boundary);
    $this->assertModelExists($recent);
    $this->assertModelExists($future);
});

it('sends a test email through the artisan command using a normalized address', function () {
    Mail::fake();

    $this->artisan('mail:test', ['email' => ' Test@Example.com '])->expectsOutput('Correo de prueba enviado.')->assertSuccessful();

    Mail::assertSent(TestMail::class, fn (TestMail $mail): bool => $mail->hasTo('test@example.com'));
});

it('rejects invalid test mail recipients without sending mail', function () {
    Mail::fake();

    $this->artisan('mail:test', ['email' => 'correo inválido'])->expectsOutput('Escribe un correo electrónico válido.')->assertFailed();

    Mail::assertNothingSent();
});
