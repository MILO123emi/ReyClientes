<?php

namespace Database\Seeders;

use App\Actions\Users\CreateUser;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(CreateUser $createUser): void
    {
        if (! app()->environment('local')) {
            return;
        }

        foreach ([
            ['Administradora de Prueba', 'owner@example.com', true, false],
            ['Gestor de Prueba', 'gestion@example.com', false, true],
            ['Consulta de Prueba', 'consulta@example.com', false, false],
        ] as [$name, $email, $isOwner, $canManageCards]) {
            if (! User::query()->where('email', $email)->exists()) {
                $createUser->handle($name, $email, 'password', $isOwner, $canManageCards, mustChangePassword: false);
            }
        }
    }
}
