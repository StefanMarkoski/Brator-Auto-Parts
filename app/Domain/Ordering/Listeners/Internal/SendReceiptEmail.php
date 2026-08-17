<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Listeners\Internal;

use App\Domain\Ordering\Events\ReceiptPlaced;
use App\Domain\Ordering\Mail\ReceiptPlacedMail;
use App\Domain\Ordering\Models\Receipt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The receipt email IS the deliverable of the dummy checkout — there is no gateway to
 * confirm anything, so this is what the customer walks away with. Locally it lands in
 * Mailpit at http://localhost:8030.
 *
 * A FAILED SEND MUST NEVER REACH THE CHECKOUT CONTROLLER, and the reason is specific
 * rather than general caution. This listener runs after PlaceReceiptAction's transaction
 * has COMMITTED: the receipt exists, stock is already decremented, the basket is already
 * emptied. Symfony's TransportException extends \RuntimeException — verified in
 * vendor/symfony/mailer/Exception — and CheckoutController::place() catches
 * RuntimeException and redirects to the cart with the message. So an unreachable mail
 * server used to produce the worst possible outcome: the order was placed and paid for in
 * every sense the app understands, and the shopper was sent to an empty cart holding an
 * SMTP error, concluding it had failed. The obvious next thing they do is order again.
 *
 * This cannot happen locally, because Mailpit never refuses. It appears the moment the
 * shop is hosted anywhere real, which is exactly when nobody is watching for it.
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

        try {
            Mail::to($receipt->customer_email)->send(new ReceiptPlacedMail($receipt));
        } catch (Throwable $e) {
            // Throwable rather than TransportException: a broken mail VIEW would throw a
            // ViewException, and losing a placed order to a typo in a Blade template is the
            // same failure with a different name. The receipt page still renders — the
            // shopper gets their confirmation, it just does not also arrive by email.
            Log::error('ordering.send_receipt_email.failed', [
                'receipt_number' => $receipt->receipt_number,
                'to' => $receipt->customer_email,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        Log::info('ordering.send_receipt_email.sent', [
            'receipt_number' => $receipt->receipt_number,
            'to' => $receipt->customer_email,
        ]);
    }
}
