<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTOs;

use App\Support\ValueObjects\Money;

final readonly class BasketLineSummary
{
    public function __construct(
        public string $lineId,
        public string $productId,
        public string $productSlug,
        public string $productName,
        public string $productSku,
        public ?string $brandName,
        public string $imagePath,
        public Money $unitPrice,
        public int $quantity,
        public Money $lineTotal,
        public bool $inStock,
        public int $stockAvailable,
    ) {}
}
