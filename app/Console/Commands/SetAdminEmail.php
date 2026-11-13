<?php

namespace App\Console\Commands;

use App\Actions\Users\ChangeUserEmail;
use App\Models\User;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SetAdminEmail extends Command
{
    protected $signature = 'admin:set-email
        {email? : Correo actual del usuario}
        {new-email? : Correo nuevo}
        {--owner : Cambiar el correo de la administradora principal sin escribir el actual}';

    protected $description = 'Cambiar el correo de un usuario y cerrar sus sesiones';

    public function handle(ChangeUserEmail $changeEmail): int
    {
        $current = $this->argument('email');
        $new = $this->argument('new-email');
        if ($this->option('owner') && $new === null) {
            [$current, $new] = [null, $current];
        }

        $user = $this->option('owner')
            ? User::query()->where('is_owner', true)->first()
            : User::query()->where('email', Str::lower(trim((string) $current)))->first();

        if ($user === null) {
            $this->error($this->option('owner') ? 'No existe una administradora principal.' : 'No existe un usuario con ese correo electrónico.');

            return self::FAILURE;
        }

        $new = Str::lower(trim((string) ($new ?? $this->ask('Correo nuevo'))));
        $validator = Validator::make(['email' => $new], ['email' => ['required', 'string', 'email', 'max:255']]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (! $this->confirm("¿Cambiar el correo de {$user->name} de {$user->email} a {$new}?", true)) {
            return self::FAILURE;
        }

        try {
            $changeEmail->handle($user, $new, fromConsole: true);
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Correo actualizado. Se cerraron todas las sesiones del usuario.');

        return self::SUCCESS;
    }
}
