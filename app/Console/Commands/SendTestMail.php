<?php

namespace App\Console\Commands;

use App\Mail\TestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class SendTestMail extends Command
{
    protected $signature = 'mail:test {email : Correo al que se enviará la prueba}';

    protected $description = 'Enviar un correo de prueba de Super El Rey';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']]);
        if ($validator->fails()) {
            $this->error('Escribe un correo electrónico válido.');

            return self::FAILURE;
        }

        try {
            Mail::to($email)->send(new TestMail);
        } catch (Throwable $exception) {
            $this->error('No se pudo enviar el correo de prueba. Revisa la configuración del correo.');
            // Con -v se ve el motivo exacto (útil al configurar el servidor).
            $this->line($exception->getMessage(), verbosity: 'v');

            return self::FAILURE;
        }

        $this->info('Correo de prueba enviado.');

        return self::SUCCESS;
    }
}
