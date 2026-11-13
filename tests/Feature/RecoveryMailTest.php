<?php

use App\Actions\Recovery\SendOwnerResetCode;
use App\Http\Controllers\OwnerPasswordResetController;
use App\Mail\PasswordResetCode as PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

it('delivers recovery mail synchronously with Spanish content and an embedded logo', function () {
    Mail::fake();
    Log::spy();
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);

    app(SendOwnerResetCode::class)->handle($owner->email);

    Mail::assertSent(PasswordResetCodeMail::class, 1);
    Mail::assertNothingQueued();
    $mail = Mail::sent(PasswordResetCodeMail::class)->sole();
    $mail->assertHasSubject('Código para recuperar tu contraseña — Super El Rey');
    $mail->assertSeeInHtml('Vence en 10 minutos.');
    $mail->assertSeeInHtml('Si no lo pediste, ignora este correo.');
    $mail->assertSeeInHtml($mail->code);
    $html = $mail->render();
    expect(str_contains($html, 'src="data:image/png;base64,'))->toBeTrue();
    expect(str_contains($html, 'alt="Super El Rey"'))->toBeTrue();
    Log::shouldNotHaveReceived('debug');
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('error');
});

it('rejects log mail transports outside local without persisting or sending a reset code', function (string $mailer) {
    Mail::fake();
    $this->app->detectEnvironment(fn () => 'staging');
    config()->set([
        'mail.default' => $mailer,
        'mail.mailers.recovery-failover' => ['transport' => 'failover', 'mailers' => ['array', 'log']],
        'mail.mailers.recovery-roundrobin' => ['transport' => 'roundrobin', 'mailers' => ['array', 'recovery-failover']],
    ]);
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);

    expect(fn () => app(SendOwnerResetCode::class)->handle($owner->email))
        ->toThrow(RuntimeException::class, 'El correo de recuperación no puede usar registros fuera del entorno local.');

    $this->assertDatabaseCount('password_reset_codes', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    Mail::assertNothingOutgoing();
})->with(['log', 'recovery-failover', 'recovery-roundrobin']);

it('rejects log mail transports outside local for any email so the response does not reveal the owner', function () {
    Mail::fake();
    $this->app->detectEnvironment(fn () => 'staging');
    config()->set('mail.default', 'log');

    expect(fn () => app(SendOwnerResetCode::class)->handle('missing@example.com'))
        ->toThrow(RuntimeException::class, 'El correo de recuperación no puede usar registros fuera del entorno local.');
});

it('records no sent audit when the mail transport fails after the code is issued', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Detalle privado del transporte'));
    $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);

    expect(fn () => app(SendOwnerResetCode::class)->handle($owner->email))->toThrow(RuntimeException::class);

    $this->assertDatabaseCount('password_reset_codes', 1);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('preserves the generic public response and logs no private transport exception', function () {
    Log::spy();
    $privateFailure = 'Contenido privado del transporte que no debe registrarse';
    $this->mock(SendOwnerResetCode::class)->shouldReceive('handle')->twice()
        ->andThrow(new RuntimeException($privateFailure));

    foreach (['owner@example.com', 'missing@example.com'] as $email) {
        $this->post(route('recovery.send'), ['email' => $email])
            ->assertRedirect(route('recovery.verify'))
            ->assertSessionHas('status', OwnerPasswordResetController::STATUS);
    }

    Log::shouldHaveReceived('error')->twice()->with('No se pudo enviar el correo de recuperación.');
    Log::shouldNotHaveReceived('error', [$privateFailure]);
    $this->assertDatabaseCount('password_reset_codes', 0);
});

it('reports a fixed Spanish error when sending the test mail fails', function () {
    Mail::shouldReceive('to')->once()->with('test@example.com')->andThrow(new RuntimeException('Detalle privado del transporte'));

    $this->artisan('mail:test', ['email' => 'test@example.com'])
        ->expectsOutput('No se pudo enviar el correo de prueba. Revisa la configuración del correo.')
        ->doesntExpectOutputToContain('Detalle privado del transporte')
        ->assertFailed();
});
