<?php

use App\Actions\Audit\RecordAudit;
use App\Actions\Users\ChangeOwnPassword;
use App\Actions\Users\CreateUser;
use App\Actions\Users\SetUserPassword;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('records the authenticated actor IP and client morph subject', function () {
    $this->travelTo(now()->startOfSecond());
    $actor = User::factory()->manager()->create();
    $client = Client::factory()->create();
    $this->actingAs($actor);
    request()->server->set('REMOTE_ADDR', '192.0.2.42');

    $log = app(RecordAudit::class)->handle(AuditAction::ClientCreated, 'Cliente creado: Nombre de Prueba', $client);

    $this->assertDatabaseHas('audit_logs', [
        'id' => $log->id, 'user_id' => $actor->id, 'action' => 'client.created',
        'subject_type' => 'client', 'subject_id' => $client->id, 'ip_address' => '192.0.2.42',
        'description' => 'Cliente creado: Nombre de Prueba',
    ]);
    expect($log->user->is($actor))->toBeTrue();
    expect($log->subject->is($client))->toBeTrue();
    expect($log->refresh()->created_at->equalTo(now()))->toBeTrue();
});

it('accepts an explicit actor and an authorized person morph subject', function () {
    $actor = User::factory()->create();
    $person = AuthorizedPerson::factory()->create();

    $log = app(RecordAudit::class)->handle(AuditAction::PersonCreated, 'Persona autorizada agregada', $person, $actor);

    expect($log->user_id)->toBe($actor->id);
    expect($log->subject_type)->toBe('person');
    expect($log->subject->is($person))->toBeTrue();
});

it('accepts anonymous events without a subject', function () {
    $log = app(RecordAudit::class)->handle(AuditAction::AuthLoginFailed, 'Inicio de sesión fallido');

    expect($log->user_id)->toBeNull();
    expect($log->subject_type)->toBeNull();
    expect($log->subject_id)->toBeNull();
});

it('limits descriptions to the database column length', function () {
    $log = app(RecordAudit::class)->handle(AuditAction::ClientUpdated, str_repeat('á', 300));

    expect(mb_strlen($log->refresh()->description))->toBe(255);
});

it('rejects full identities without repeating them in exceptions', function (string $dni) {
    expect(fn () => app(RecordAudit::class)->handle(AuditAction::ClientCreated, 'Cliente: '.$dni))
        ->toThrow(function (InvalidArgumentException $exception) use ($dni) {
            expect($exception->getMessage())->toBe('La descripción de auditoría no puede incluir una identidad completa.')
                ->not->toContain($dni);
        });
    $this->assertDatabaseCount('audit_logs', 0);
})->with(['0801199012345', '0801-1990-12345', '0801 1990 12345']);

it('checks for identities before truncating the description', function () {
    expect(fn () => app(RecordAudit::class)->handle(AuditAction::ClientCreated, str_repeat('a', 300).'0801199012345'))
        ->toThrow(InvalidArgumentException::class);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('accepts masked identities in audit descriptions', function () {
    $log = app(RecordAudit::class)->handle(AuditAction::ClientUpdated, 'Cliente editado: ****-****-*2345');

    expect($log->description)->toBe('Cliente editado: ****-****-*2345');
});

it('prevents audit records from being updated or deleted', function (string $operation) {
    $log = app(RecordAudit::class)->handle(AuditAction::AuthLogin, 'Inicio de sesión');

    expect(fn () => $operation === 'update' ? $log->forceFill(['description' => 'Alterado'])->save() : $log->delete())
        ->toThrow(LogicException::class, 'Los registros de auditoría no se pueden modificar.');
    $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'description' => 'Inicio de sesión']);
})->with(['update', 'delete']);

it('retains audit records and clears the actor after deleting the user', function () {
    $actor = User::factory()->create();
    $log = app(RecordAudit::class)->handle(AuditAction::AuthLogin, 'Inicio de sesión', actor: $actor);

    $actor->delete();

    $this->assertModelExists($log);
    expect($log->refresh()->user_id)->toBeNull();
});

it('audits user creation within the action', function () {
    $actor = User::factory()->owner()->create();
    $this->actingAs($actor);

    $user = app(CreateUser::class)->handle('Gestor de Prueba', 'gestor@example.com', 'abcdefgh', false, true, true);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $actor->id, 'action' => 'user.created', 'subject_type' => 'user', 'subject_id' => $user->id,
        'description' => 'Usuario creado: Gestor de Prueba (gestor@example.com), tipo Gestión',
    ]);
});

it('audits password assignment without including the password', function () {
    $actor = User::factory()->owner()->create();
    $user = User::factory()->create(['name' => 'Consulta de Prueba', 'email' => 'consulta@example.com']);
    $this->actingAs($actor);

    app(SetUserPassword::class)->handle($user, 'temporary-password', true);

    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('user.password_set_by_owner');
    expect($log->user_id)->toBe($actor->id);
    expect($log->subject_type)->toBe('user');
    expect($log->subject_id)->toBe($user->id);
    expect($log->description)->toBe('Contraseña asignada a Consulta de Prueba (consulta@example.com)')
        ->not->toContain('temporary-password');
});

it('does not audit own password changes', function () {
    $user = User::factory()->create();

    app(ChangeOwnPassword::class)->handle($user, 'new-password');

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
    $this->assertDatabaseCount('audit_logs', 0);
});

it('records console user creation with no actor', function () {
    $this->artisan('admin:create', ['--owner' => true])
        ->expectsQuestion('Nombre', 'Principal de Prueba')
        ->expectsQuestion('Correo electrónico', 'principal@example.com')
        ->expectsQuestion('Contraseña', 'abcdefgh')
        ->expectsQuestion('Confirma la contraseña', 'abcdefgh')
        ->assertSuccessful();

    $user = User::query()->sole();
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => null, 'action' => 'user.created', 'subject_type' => 'user', 'subject_id' => $user->id,
        'description' => 'Usuario creado: Principal de Prueba (principal@example.com), tipo Principal',
    ]);
});

it('rolls back user creation when auditing fails', function () {
    expect(fn () => app(CreateUser::class)->handle('Prueba 0801199012345', 'test@example.com', 'abcdefgh', false, false, true))
        ->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('rolls back password and session changes when auditing fails', function () {
    $user = User::factory()->create(['name' => 'Prueba 0801199012345']);
    $hash = $user->password;
    DB::table('sessions')->insert(['id' => 'preserved', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    expect(fn () => app(SetUserPassword::class)->handle($user, 'temporary-password', true))
        ->toThrow(InvalidArgumentException::class);

    expect($user->refresh()->password)->toBe($hash);
    expect($user->must_change_password)->toBeFalse();
    $this->assertDatabaseHas('sessions', ['id' => 'preserved']);
    $this->assertDatabaseCount('audit_logs', 0);
});
