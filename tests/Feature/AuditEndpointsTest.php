<?php

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

it('enforces the role matrix for clients users and audit pages', function (string $role, string $route) {
    $user = match ($role) {
        'owner' => User::factory()->owner()->create(),
        default => User::factory()->create(),
    };

    $response = $this->actingAs($user)->get(route($route));

    if ($route === 'clients.index' || $role === 'owner') {
        $response->assertOk();
    } else {
        $response->assertForbidden();
    }
})->with(['employee', 'owner'])->with(['clients.index', 'users.index', 'audit.index']);

it('requires authentication for administration pages', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['users.index', 'audit.index']);

it('lists fifty audit records per page newest first with safe labeled resource fields', function () {
    $this->travelTo(now()->startOfSecond());
    $owner = User::factory()->owner()->create(['name' => 'Principal De Prueba']);
    for ($index = 0; $index < 51; $index++) {
        app(RecordAudit::class)->handle(AuditAction::ClientCreated, 'Registro de prueba '.$index, actor: $owner);
    }

    $response = $this->actingAs($owner)->get(route('audit.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('audit/Index')->has('logs.data', 50)
            ->where('logs.meta.per_page', 50)->where('logs.meta.last_page', 2)
            ->where('logs.data.0.description', 'Registro de prueba 50')->where('logs.data.0.user', 'Principal De Prueba')
            ->where('logs.data.0.action_label', 'Cliente creado')->has('actions', 18));

    expect($response->inertiaProps('logs.data.0'))->toHaveKeys([
        'created_at', 'user', 'action_label', 'description', 'ip_address',
    ])->toHaveCount(5);
    $this->get(route('audit.index', ['page' => 2]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.description', 'Registro de prueba 0'));
});

it('filters audit events by action and retains the action on pagination links', function () {
    $owner = User::factory()->owner()->create();
    app(RecordAudit::class)->handle(AuditAction::ClientCreated, 'Cliente creado de prueba', actor: $owner);
    app(RecordAudit::class)->handle(AuditAction::PersonDeleted, 'Persona eliminada de prueba', actor: $owner);

    $response = $this->actingAs($owner)->get(route('audit.index', ['action' => 'client.created']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.description', 'Cliente creado de prueba')->where('filters.action', 'client.created'));

    expect($response->inertiaProps('logs.links.first'))->toContain('action=client.created');
});

it('interprets audit date filters as complete Honduras calendar days', function () {
    $owner = User::factory()->owner()->create();
    foreach ([
        ['2026-11-12 05:59:59', 'Antes del día'],
        ['2026-11-12 06:00:00', 'Primero del día'],
        ['2026-11-13 05:59:59', 'Último del día'],
        ['2026-11-13 06:00:00', 'Después del día'],
    ] as [$time, $description]) {
        $this->travelTo(CarbonImmutable::parse($time, 'UTC'));
        app(RecordAudit::class)->handle(AuditAction::ClientCreated, $description, actor: $owner);
    }

    $this->actingAs($owner)->get(route('audit.index', ['from' => '2026-11-12', 'to' => '2026-11-12']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 2)
            ->where('logs.data.0.description', 'Último del día')->where('logs.data.1.description', 'Primero del día'));
});

it('validates audit filters before querying records', function (array $filters, string $field) {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get(route('audit.index', $filters))->assertSessionHasErrors($field);
})->with([
    'unknown action' => [['action' => 'client.unknown'], 'action'],
    'invalid start date' => [['from' => '12-11-2026'], 'from'],
    'invalid end date' => [['to' => 'fecha'], 'to'],
    'end before start' => [['from' => '2026-11-13', 'to' => '2026-11-12'], 'to'],
]);

it('labels anonymous audit actors with an em dash', function () {
    $owner = User::factory()->owner()->create();
    app(RecordAudit::class)->handle(AuditAction::AuthLoginFailed, 'Intento fallido de prueba', actor: null);

    $this->actingAs($owner)->get(route('audit.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('logs.data.0.user', '—'));
});

it('audits successful login without including the password', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);

    $this->post(route('login.store'), ['email' => 'User@Example.com', 'password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect('/clientes');

    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('auth.login');
    expect($log->user_id)->toBe($user->id);
    expect($log->description)->toBe('Inicio de sesión de user@example.com')->not->toContain('password');
});

it('audits failed login anonymously with a normalized email and no password', function () {
    User::factory()->create(['email' => 'user@example.com']);

    $this->post(route('login.store'), ['email' => 'User@Example.com', 'password' => 'incorrect-secret'])
        ->assertSessionHasErrors('email');

    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('auth.login_failed');
    expect($log->user_id)->toBeNull();
    expect($log->description)->toBe('Intento fallido para user@example.com')->not->toContain('incorrect-secret');
});

it('masks full identity sequences embedded in login email addresses', function (bool $successful) {
    $email = '0801-1990-12345@example.com';
    if ($successful) {
        User::factory()->create(['email' => $email]);
    }

    $response = $this->post(route('login.store'), ['email' => $email, 'password' => 'password']);

    if ($successful) {
        $response->assertSessionHasNoErrors();
    } else {
        $response->assertSessionHasErrors('email');
    }
    expect(AuditLog::query()->sole()->description)->toContain('****-****-*2345@example.com')
        ->not->toContain('0801199012345', '0801-1990-12345', 'password');
})->with([true, false]);

it('keeps historic permission events readable after removing the permission', function () {
    $owner = User::factory()->owner()->create();
    AuditLog::query()->forceCreate([
        'user_id' => $owner->id, 'action' => 'user.permission_changed',
        'description' => 'Permiso anterior de prueba', 'created_at' => now(),
    ]);
    $this->actingAs($owner)->get(route('audit.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('logs.data.0.action_label', 'Permiso cambiado'));
});
