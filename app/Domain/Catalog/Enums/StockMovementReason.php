<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum StockMovementReason: string
{
    case Import = 'import';
    case ManualAdjustment = 'manual_adjustment';
    case Sale = 'sale';
    case Cancellation = 'cancellation';
    case Stocktake = 'stocktake';
}
