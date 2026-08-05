<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Payload is the id only, per the house spec — listeners re-read what they need. */
final class ReceiptPlaced
{
    use Dispatchable;

    public function __construct(public readonly string $receiptId) {}
}
