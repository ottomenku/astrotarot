<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReadingExportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{locale: string, question: string, cards: list<array<string, mixed>>, answers: list<array{mode: string, label: string, text: string}>}  $payload
     */
    public function __construct(
        public array $payload,
        public string $pdfBinary,
    ) {
    }

    public function envelope(): Envelope
    {
        $locale = $this->payload['locale'] ?? 'hu';

        return new Envelope(
            subject: $locale === 'en'
                ? 'Your AstroTarot reading'
                : 'Az AstroTarot olvasatod',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: view('exports.reading-mail', [
                'payload' => $this->payload,
            ])->render(),
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBinary, 'astrotarot-olvasat.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
