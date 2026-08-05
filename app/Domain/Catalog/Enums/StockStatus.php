<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum StockStatus: string
{
    case InStock = 'in_stock';
    case OutOfStock = 'out_of_stock';
    case OnBackorder = 'on_backorder';

    public function isBuyable(): bool
    {
        return $this !== self::OutOfStock;
    }

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::OutOfStock => 'Out of Stock',
            self::OnBackorder => 'On Backorder',
        };
    }
}
