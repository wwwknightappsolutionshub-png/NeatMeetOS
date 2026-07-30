<?php

namespace App\Domains\AiHairstyle\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AiHairstyleLookDeclinedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $salonName,
        public readonly string $guestFirstName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update on your AI look — '.$this->salonName,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Hi '.e($this->guestFirstName).',</p>'
                .'<p>'.e($this->salonName).' reviewed your AI hairstyle look and is not moving forward with it this time. '
                .'Feel free to try another look when you book.</p>',
        );
    }
}
