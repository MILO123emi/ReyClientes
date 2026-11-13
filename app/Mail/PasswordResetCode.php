<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetCode extends Mailable
{
    public function __construct(#[\SensitiveParameter] public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Código para recuperar tu contraseña — Super El Rey');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.password-reset-code');
    }
}
