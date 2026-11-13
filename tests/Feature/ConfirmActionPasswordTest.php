<?php

use App\Http\Requests\ConfirmedRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class TestConfirmedMutationRequest extends ConfirmedRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageCards() ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...$this->confirmationRules(),
            'name' => ['required', 'string', 'min:3'],
        ];
    }
}

beforeEach(function () {
    Route::middleware(['web', 'auth'])->post('/_tests/confirmed-mutation', function (TestConfirmedMutationRequest $request) {
        return response()->json($request->payload());
    });
});

it('requires authentication before confirming an action', function () {
    $this->post('/_tests/confirmed-mutation', ['current_password' => 'password', 'name' => 'Nombre de Prueba'])
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('audit_logs', 0);
});

it('rejects missing empty and non-string passwords without counting failures', function (array $passwordInput) {
    $user = User::factory()->manager()->create();

    $this->actingAs($user)->post('/_tests/confirmed-mutation', [
        'name' => 'Nombre de Prueba',
        ...$passwordInput,
    ])->assertSessionHasErrors(['current_password' => 'Escribe tu contraseña para confirmar.'])
        ->assertSessionMissing('confirm_failures');

    expect(RateLimiter::attempts('confirm:'.$user->id))->toBe(0);
    $this->assertDatabaseCount('audit_logs', 0);
})->with([
    'missing' => [[]],
    'empty' => [['current_password' => '']],
    'null' => [['current_password' => null]],
    'number' => [['current_password' => 12345]],
    'array' => [['current_password' => ['password']]],
]);

it('counts incorrect passwords and audits failure without exposing the password', function () {
    $user = User::factory()->manager()->create();

    $this->actingAs($user)->post('/_tests/confirmed-mutation', [
        'current_password' => 'incorrect-secret', 'name' => 'Nombre de Prueba',
    ])->assertSessionHasErrors(['current_password' => 'La contraseña es incorrecta.'])
        ->assertSessionHas('confirm_failures', 1);

    expect(RateLimiter::attempts('confirm:'.$user->id))->toBe(1);
    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('auth.confirm_failed');
    expect($log->user_id)->toBe($user->id);
    expect($log->description)->toBe('Contraseña incorrecta al confirmar una acción')
        ->not->toContain('incorrect-secret');
});

it('returns 429 after five failures even for a correct password and permits retry after expiry', function () {
    $this->travelTo(now()->startOfSecond());
    $user = User::factory()->manager()->create();
    $this->actingAs($user);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/_tests/confirmed-mutation', [
            'current_password' => 'incorrect-secret', 'name' => 'Nombre de Prueba',
        ])->assertSessionHasErrors('current_password');
    }

    $this->post('/_tests/confirmed-mutation', [
        'current_password' => 'password', 'name' => 'Nombre de Prueba',
    ])->assertTooManyRequests()->assertHeader('Retry-After', '300')
        ->assertSessionHas('confirm_failures', 5);

    $this->assertDatabaseCount('audit_logs', 5);
    $this->travel(301)->seconds();
    $this->post('/_tests/confirmed-mutation', [
        'current_password' => 'password', 'name' => 'Nombre de Prueba',
    ])->assertOk()->assertExactJson(['name' => 'Nombre de Prueba'])
        ->assertSessionHas('confirm_failures', 5);
});

it('clears the limiter after a correct password without resetting cumulative session failures', function () {
    $user = User::factory()->manager()->create();
    $this->actingAs($user);
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->post('/_tests/confirmed-mutation', [
            'current_password' => 'incorrect-secret', 'name' => 'Nombre de Prueba',
        ])->assertSessionHasErrors('current_password');
    }

    $this->post('/_tests/confirmed-mutation', [
        'current_password' => 'password', 'name' => 'Nombre de Prueba', 'is_owner' => true,
    ])->assertOk()->assertExactJson(['name' => 'Nombre de Prueba'])
        ->assertSessionHas('confirm_failures', 3);

    expect(RateLimiter::attempts('confirm:'.$user->id))->toBe(0);
    $this->assertDatabaseCount('audit_logs', 3);
});

it('logs out and invalidates the session after ten cumulative failures', function () {
    $this->travelTo(now()->startOfSecond());
    $user = User::factory()->manager()->create();
    $this->actingAs($user)->withSession(['_token' => 'initial-token']);
    for ($attempt = 0; $attempt < 9; $attempt++) {
        if ($attempt === 5) {
            $this->travel(301)->seconds();
        }

        $this->post('/_tests/confirmed-mutation', [
            'current_password' => 'incorrect-secret', 'name' => 'Nombre de Prueba',
        ])->assertSessionHasErrors('current_password')
            ->assertSessionHas('confirm_failures', $attempt + 1);
    }

    $this->post('/_tests/confirmed-mutation', [
        'current_password' => 'incorrect-secret', 'name' => 'Nombre de Prueba',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Se cerró la sesión por demasiados intentos fallidos.'])
        ->assertSessionMissing('confirm_failures');

    $this->assertGuest('web');
    expect(session()->token())->not->toBe('initial-token');
    $this->assertDatabaseCount('audit_logs', 10);
});

it('returns 403 for unauthorized users without counting password failures', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/_tests/confirmed-mutation', [
        'current_password' => 'incorrect-secret', 'name' => 'Nombre de Prueba',
    ])->assertForbidden()->assertSessionMissing('confirm_failures');

    expect(RateLimiter::attempts('confirm:'.$user->id))->toBe(0);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('checks authorization before applying the confirmation throttle', function () {
    $user = User::factory()->create();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        RateLimiter::hit('confirm:'.$user->id, 300);
    }

    $this->actingAs($user)->post('/_tests/confirmed-mutation', [
        'current_password' => 'incorrect-secret', 'name' => 'Nombre de Prueba',
    ])->assertForbidden()->assertSessionMissing('confirm_failures');

    expect(RateLimiter::attempts('confirm:'.$user->id))->toBe(5);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('confirms the password before validating other fields', function () {
    $user = User::factory()->manager()->create();

    $this->actingAs($user)->post('/_tests/confirmed-mutation', [
        'current_password' => 'incorrect-secret', 'name' => '',
    ])->assertSessionHasErrors(['current_password' => 'La contraseña es incorrecta.'])
        ->assertSessionDoesntHaveErrors('name');

    $this->assertDatabaseCount('audit_logs', 1);
});

it('validates other fields after a correct confirmation', function () {
    $user = User::factory()->manager()->create();

    $this->actingAs($user)->post('/_tests/confirmed-mutation', [
        'current_password' => 'password', 'name' => '',
    ])->assertSessionHasErrors('name')->assertSessionDoesntHaveErrors('current_password');

    $this->assertDatabaseCount('audit_logs', 0);
});
