<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Correo de prueba — Super El Rey');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.test');
    }
}
