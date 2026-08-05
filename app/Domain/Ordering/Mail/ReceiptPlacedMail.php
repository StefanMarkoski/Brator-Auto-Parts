<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Mail;

use App\Domain\Ordering\Models\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ReceiptPlacedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Receipt $receipt) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Brator receipt {$this->receipt->receipt_number}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.receipt');
    }
}
