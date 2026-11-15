<?php

namespace App\Console\Commands;

use App\Actions\Users\SetUserPassword;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password
        {email? : Correo electrónico del usuario}
        {--owner : Cambiar la contraseña de la administradora principal sin escribir su correo}';

    protected $description = 'Asignar una contraseña y cerrar las sesiones del usuario';

    public function handle(SetUserPassword $setPassword): int
    {
        if ($this->option('owner')) {
            $user = User::query()->where('is_owner', true)->first();
        } else {
            $email = Str::lower(trim((string) $this->argument('email')));
            if ($email === '') {
                $this->error('Escribe el correo del usuario o usa --owner para la administradora principal.');

                return self::FAILURE;
            }

            $user = User::query()->where('email', $email)->first();
        }

        if ($user === null) {
            $this->error($this->option('owner') ? 'No existe una administradora principal.' : 'No existe un usuario con ese correo electrónico.');

            return self::FAILURE;
        }

        $this->line("Usuario: {$user->name} ({$user->email})");

        $password = (string) $this->secret('Nueva contraseña', fallback: false);
        $confirmation = (string) $this->secret('Confirma la contraseña', fallback: false);
        $validator = Validator::make([
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $setPassword->handle($user, $password, mustChange: ! $user->isOwner());
        $this->info('Contraseña actualizada. Se cerraron todas las sesiones del usuario.');

        return self::SUCCESS;
    }
}
