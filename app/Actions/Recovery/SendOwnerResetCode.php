<?php

namespace App\Actions\Recovery;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Mail\PasswordResetCode as PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class SendOwnerResetCode
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(string $email): void
    {
        if (! app()->environment('local') && $this->usesLogTransport((string) config('mail.default'))) {
            throw new RuntimeException('El correo de recuperación no puede usar registros fuera del entorno local.');
        }

        /** @var array{owner: User, code: string}|null $issued */
        $issued = DB::transaction(function () use ($email): ?array {
            $owner = User::query()->where('email', Str::lower(trim($email)))->where('is_owner', true)->lockForUpdate()->first();
            if ($owner === null) {
                return null;
            }

            PasswordResetCode::query()->where('user_id', $owner->id)->delete();
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            (new PasswordResetCode)->forceFill([
                'user_id' => $owner->id,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
            ])->save();

            return ['owner' => $owner, 'code' => $code];
        });

        if ($issued === null) {
            return;
        }

        // SMTP runs outside the transaction so a slow mail server never holds the SQLite write lock.
        Mail::to($issued['owner']->email)->send(new PasswordResetCodeMail($issued['code']));
        DB::transaction(fn () => $this->audit->handle(AuditAction::AuthResetCodeSent, 'Código de recuperación enviado a la cuenta principal', $issued['owner']));
    }

    /** @param list<string> $visited */
    private function usesLogTransport(string $mailer, array $visited = []): bool
    {
        if (in_array($mailer, $visited, true)) {
            return false;
        }

        $transport = config("mail.mailers.{$mailer}.transport");
        if ($transport === 'log') {
            return true;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = config("mail.mailers.{$mailer}.mailers", []);
            if (is_array($children)) {
                foreach ($children as $child) {
                    if (is_string($child) && $this->usesLogTransport($child, [...$visited, $mailer])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
