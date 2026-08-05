<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Enums;

enum ReceiptStatus: string
{
    case Pending = 'pending';
    /** The fake payment step marks a receipt paid. A real gateway would set the same state. */
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }
}
