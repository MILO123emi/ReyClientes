<?php

namespace Database\Seeders;

use App\Actions\Users\CreateUser;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(CreateUser $createUser): void
    {
        if (! app()->environment('local')) {
            return;
        }

        foreach ([
            ['Administradora de Prueba', 'owner@example.com', true],
            ['Empleado de Prueba', 'empleado@example.com', false],
        ] as [$name, $email, $isOwner]) {
            if (! User::query()->where('email', $email)->exists()) {
                $createUser->handle($name, $email, 'password', $isOwner, mustChangePassword: false);
            }
        }

        if (Client::query()->count() === 0) {
            $owner = User::query()->where('is_owner', true)->sole();

            Client::factory()->withAvatarPhoto()->count(15)->create(['created_by' => $owner->id])
                ->each(function (Client $client) use ($owner): void {
                    AuthorizedPerson::factory()->withAvatarPhoto()->count(random_int(0, 4))->for($client)
                        ->create(['created_by' => $owner->id]);
                });
        }
    }
}
