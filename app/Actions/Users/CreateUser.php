<?php

namespace App\Actions\Users;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateUser
{
    public function handle(string $name, string $email, string $password, bool $isOwner, bool $canManageCards, bool $mustChangePassword): User
    {
        return DB::transaction(function () use ($name, $email, $password, $isOwner, $canManageCards, $mustChangePassword): User {
            if ($isOwner && User::query()->where('is_owner', true)->lockForUpdate()->first() !== null) {
                throw new DomainException('Ya existe una administradora principal.');
            }

            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => Str::lower(trim($email)),
                'password' => $password,
                'is_owner' => $isOwner,
                'can_manage_cards' => $canManageCards,
                'must_change_password' => $mustChangePassword,
            ])->save();

            return $user;
        }, 3);
    }
}
