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
    protected $signature = 'admin:reset-password {email : Correo electrónico del usuario}';

    protected $description = 'Asignar una contraseña y cerrar las sesiones del usuario';

    public function handle(SetUserPassword $setPassword): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error('No existe un usuario con ese correo electrónico.');

            return self::FAILURE;
        }

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
