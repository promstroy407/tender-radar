<?php

declare(strict_types=1);

namespace App\Mail;

use App\Data\RenderedOffer;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Письмо победителю с нашим КП. Обычный текст — так письмо похоже на личное, а не на рассылку. */
class KpMail extends Mailable
{
    public function __construct(
        public readonly RenderedOffer $offer,
        public readonly ?string $attachmentPath = null,
        public readonly string $attachmentName = 'КП.pdf',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->offer->subject);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.kp', with: ['body' => $this->offer->body]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if ($this->attachmentPath === null) {
            return [];
        }

        return [
            Attachment::fromPath($this->attachmentPath)
                ->as($this->attachmentName)
                ->withMime('application/pdf'),
        ];
    }
}
