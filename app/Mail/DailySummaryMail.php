<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/** Ringkasan harian untuk owner (tanpa data pasien). */
class DailySummaryMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @param array{date: Carbon, branches: list<array<string, mixed>>, total: array<string, int|null>} $summary */
    public function __construct(public array $summary) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ringkasan '.config('app.name').' · '.$this->summary['date']->locale('id')->translatedFormat('l, j F Y'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.daily-summary');
    }
}
