<?php

namespace App\Console\Commands;

use App\Actions\Users\CreateUser;
use App\Models\User;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {--owner : Crear la administradora principal}';

    protected $description = 'Crear un usuario o la administradora principal';

    public function handle(CreateUser $createUser): int
    {
        $isOwner = (bool) $this->option('owner');

        if ($isOwner && User::query()->where('is_owner', true)->exists()) {
            $this->error('Ya existe una administradora principal.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Nombre'));
        $email = Str::lower(trim((string) $this->ask('Correo electrónico')));
        $password = (string) $this->secret('Contraseña', fallback: false);
        $confirmation = (string) $this->secret('Confirma la contraseña', fallback: false);
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            $createUser->handle($name, $email, $password, $isOwner, ! $isOwner);
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($isOwner ? 'Administradora principal creada.' : 'Usuario creado. Debe cambiar su contraseña al iniciar sesión.');

        return self::SUCCESS;
    }
}
