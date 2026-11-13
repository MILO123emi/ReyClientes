<?php

namespace App\Actions\Account;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Mail\EmailChangeCode;
use App\Models\EmailChangeRequest;
use App\Models\User;
use App\Support\MailTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RequestEmailChange
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * The address only changes after the code sent to it is confirmed, so a typo cannot lock the account out.
     *
     * @throws Throwable when the message could not be sent; no pending request is left behind
     */
    public function handle(User $user, string $email): void
    {
        if (! app()->environment('local') && MailTransport::usesLog()) {
            throw new RuntimeException('El código de verificación no puede usar registros fuera del entorno local.');
        }

        $email = Str::lower(trim($email));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($user, $email, $code): void {
            EmailChangeRequest::query()->where('user_id', $user->id)->delete();
            (new EmailChangeRequest)->forceFill([
                'user_id' => $user->id,
                'new_email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
            ])->save();
        });

        try {
            // Sent outside the transaction so a slow mail server never holds the SQLite write lock.
            Mail::to($email)->send(new EmailChangeCode($code));
        } catch (Throwable $exception) {
            EmailChangeRequest::query()->where('user_id', $user->id)->delete();

            throw $exception;
        }

        DB::transaction(fn () => $this->audit->handle(
            AuditAction::UserEmailChangeRequested,
            "Código para cambiar el correo de {$user->name} enviado a {$email}",
            $user,
        ));
    }
}
