<?php

namespace App\Domains\AiHairstyle\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AiHairstyleLookAcceptedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $salonName,
        public readonly string $guestFirstName,
        public readonly string $lookLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your look was approved — '.$this->salonName,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Hi '.e($this->guestFirstName).',</p>'
                .'<p>Great news — '.e($this->salonName).' accepted '.e($this->lookLabel).'. See you soon.</p>',
        );
    }
}
