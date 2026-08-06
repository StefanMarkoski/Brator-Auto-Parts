<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Catalog\Actions\RecordStockSaleAction;
use App\Domain\Catalog\Actions\ReturnStockAction;
use App\Domain\Ordering\Enums\ReceiptStatus;
use App\Domain\Ordering\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Moves a receipt between Pending, Paid and Cancelled, and makes the shelf agree.
 *
 * Three states, by Stefan's decision — no Shipped, no Delivered, no Refunded. Cancelled is
 * therefore the ONLY unwind, which is what makes it the transition that touches stock.
 *
 * The rule is one sentence: a receipt that is not cancelled is holding its stock. So
 * entering Cancelled returns it and leaving Cancelled takes it back off the shelf; moving
 * between Pending and Paid changes nothing physical, because the goods were committed the
 * moment the order existed.
 *
 * IDEMPOTENCE comes from reading the stored status inside the transaction, under a row
 * lock, and refusing a transition to the status the receipt is already in. A double-clicked
 * Cancel button therefore credits the stock once: by the time the second request reads the
 * row, it says Cancelled and there is nothing to do. Counting movements instead would have
 * to distinguish a double click from a legitimate cancel-restore-cancel, and cannot.
 */
final class ChangeReceiptStatusAction
{
    public function __construct(
        private ReturnStockAction $returnStock,
        private RecordStockSaleAction $recordStockSale,
    ) {}

    /** @return bool whether anything changed */
    public function execute(Receipt $receipt, ReceiptStatus $to, ?string $actorId = null): bool
    {
        return DB::transaction(function () use ($receipt, $to, $actorId): bool {
            /** @var Receipt $locked */
            $locked = Receipt::query()->lockForUpdate()->findOrFail($receipt->id);
            $from = $locked->status;

            if ($from === $to) {
                return false;
            }

            $locked->loadMissing('lines');

            if ($to === ReceiptStatus::Cancelled) {
                $this->putStockBack($locked);
            } elseif ($from === ReceiptStatus::Cancelled) {
                $this->takeStockOffAgain($locked);
            }

            // Only `status` is dirty here, so the seal on the financial columns is
            // untouched — a cancelled receipt keeps the totals it was placed with, because
            // it is a record of what happened rather than a description of what is owed.
            $locked->update(['status' => $to]);

            Log::info('ordering.change_receipt_status.success', [
                'receipt_id' => $locked->id,
                'receipt_number' => $locked->receipt_number,
                'from' => $from->value,
                'to' => $to->value,
                'actor_id' => $actorId,
            ]);

            return true;
        });
    }

    private function putStockBack(Receipt $receipt): void
    {
        foreach ($receipt->lines as $line) {
            $this->returnStock->execute(
                productId: $line->product_id,
                quantity: $line->quantity,
                reference: $receipt->id,
                note: 'Receipt '.$receipt->receipt_number.' cancelled.',
            );
        }
    }

    private function takeStockOffAgain(Receipt $receipt): void
    {
        foreach ($receipt->lines as $line) {
            try {
                $this->recordStockSale->execute(
                    productId: $line->product_id,
                    quantity: $line->quantity,
                    reference: $receipt->id,
                    note: 'Receipt '.$receipt->receipt_number.' reinstated after cancellation.',
                );
            } catch (RuntimeException $e) {
                /*
                 | The stock returned by the cancellation has since been sold to someone
                 | else. Reinstating this order would oversell, so refuse the whole
                 | transition rather than let the receipt claim goods that are gone —
                 | the transaction rolls back and the receipt stays cancelled.
                */
                throw new RuntimeException(
                    "Receipt {$receipt->receipt_number} cannot be reinstated: "
                    .'the stock it released has since been sold. '.$e->getMessage()
                );
            }
        }
    }
}
