<?php

namespace App\Listeners;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Models\User;
use App\Support\Dni;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Str;

class AuditSuccessfulLogin
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        } $email = $event->user->email;
        $email = Str::limit(Str::lower(trim($email)), 120, '');
        $email = preg_replace_callback('/\d{4}\D?\d{4}\D?\d{5}/', fn (array $match): string => Dni::mask($match[0]), $email) ?? '';
        $this->audit->handle(AuditAction::AuthLogin, "Inicio de sesión de {$email}", $event->user, $event->user);
    }
}
