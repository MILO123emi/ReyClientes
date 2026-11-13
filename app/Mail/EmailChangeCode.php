<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmailChangeCode extends Mailable
{
    public function __construct(#[\SensitiveParameter] public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Código para confirmar tu nuevo correo — Super El Rey');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.email-change-code');
    }
}
