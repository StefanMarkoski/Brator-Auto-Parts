<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Listeners\Internal;

use App\Domain\Ordering\Events\ReceiptPlaced;
use App\Domain\Ordering\Mail\ReceiptPlacedMail;
use App\Domain\Ordering\Models\Receipt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The receipt email IS the deliverable of the dummy checkout — there is no gateway to
 * confirm anything, so this is what the customer walks away with. Locally it lands in
 * Mailpit at http://localhost:8030.
 */
final class SendReceiptEmail
{
    public function handle(ReceiptPlaced $event): void
    {
        $receipt = Receipt::query()->with('lines')->find($event->receiptId);

        if ($receipt === null) {
            Log::warning('ordering.send_receipt_email.receipt_missing', [
                'receipt_id' => $event->receiptId,
            ]);

            return;
        }

        Mail::to($receipt->customer_email)->send(new ReceiptPlacedMail($receipt));

        Log::info('ordering.send_receipt_email.sent', [
            'receipt_number' => $receipt->receipt_number,
            'to' => $receipt->customer_email,
        ]);
    }
}
