<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateUser
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function handle(string $name, string $email, string $password, bool $isOwner, bool $mustChangePassword): User
    {
        return DB::transaction(function () use ($name, $email, $password, $isOwner, $mustChangePassword): User {
            if ($isOwner && User::query()->where('is_owner', true)->lockForUpdate()->first() !== null) {
                throw new DomainException('Ya existe una administradora principal.');
            }

            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => Str::lower(trim($email)),
                'password' => $password,
                'is_owner' => $isOwner,
                'must_change_password' => $mustChangePassword,
            ])->save();

            $this->recordAudit->handle(
                AuditAction::UserCreated,
                "Usuario creado: {$user->name} ({$user->email}), tipo {$user->typeLabel()}",
                $user,
            );

            return $user;
        }, 3);
    }
}
