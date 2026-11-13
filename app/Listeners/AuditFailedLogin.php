<?php

namespace App\Listeners;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Support\Dni;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Str;

class AuditFailedLogin
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(Failed $event): void
    {
        $value = $event->credentials['email'] ?? '';
        $email = is_string($value) ? $value : '';
        $email = Str::limit(Str::lower(trim($email)), 120, '');
        $email = preg_replace_callback('/\d{4}\D?\d{4}\D?\d{5}/', fn (array $match): string => Dni::mask($match[0]), $email) ?? '';
        $this->audit->handle(AuditAction::AuthLoginFailed, "Intento fallido para {$email}", null, null);
    }
}
