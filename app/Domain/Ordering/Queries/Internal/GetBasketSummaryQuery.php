<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Queries\Internal;

use App\Domain\Ordering\DTOs\BasketLineSummary;
use App\Domain\Ordering\DTOs\BasketSummary;
use App\Domain\Ordering\Models\Basket;
use App\Domain\Ordering\Models\BasketLine;
use App\Domain\Ordering\Services\AppliedCoupon;

final class GetBasketSummaryQuery
{
    public function __construct(private AppliedCoupon $appliedCoupon) {}

    public function execute(?Basket $basket): BasketSummary
    {
        if ($basket === null) {
            return BasketSummary::empty();
        }

        $lines = $basket->lines
            ->map(fn (BasketLine $line): ?BasketLineSummary => $this->summarise($line))
            ->filter()
            ->values();

        /*
         | The coupon is read here, in the ONE place the basket's arithmetic is built, so the
         | cart page, the header total and the checkout cannot disagree about the discount.
         | The same reasoning that put the delivery rule in DeliveryCharge.
         |
         | Passed even when it does not currently apply — the summary keeps it so the cart can
         | say "spend a little more and this saves you X" rather than silently ignoring a code
         | the shopper has entered.
        */
        return BasketSummary::fromLines(
            $lines,
            (float) config('shop.vat_rate'),
            $this->appliedCoupon->coupon(),
        );
    }

    /** Just the badge number for the header, without loading the whole basket. */
    public function itemCount(?Basket $basket): int
    {
        if ($basket === null) {
            return 0;
        }

        return (int) $basket->lines
            ->filter(fn (BasketLine $line): bool => $line->product !== null)
            ->sum('quantity');
    }

    /**
     * One basket line, or null if its product has gone.
     *
     * A soft-deleted product nulls the relation, and reading `$product->id` on it used
     * to 500 the cart — which meant the shopper could not view the cart, could not
     * remove the offending line, and could not check out, for the thirty days the
     * basket lives. Skipping the line instead keeps the basket usable; the stale line
     * is cleaned up by PruneOrphanedBasketLines so it does not linger invisibly.
     *
     * This is the same fault family as the pinned lesson about trashed relations
     * breaking reads. Guard every level, do not assume a relation is loaded because a
     * foreign key exists.
     */
    private function summarise(BasketLine $line): ?BasketLineSummary
    {
        $product = $line->product;

        if ($product === null) {
            return null;
        }

        return new BasketLineSummary(
            lineId: $line->id,
            productId: $product->id,
            productSlug: $product->slug,
            productName: $product->name,
            productSku: $product->sku,
            brandName: $product->brand?->name,
            imagePath: $product->images->first()?->path ?? 'assets/images/shop/product-06.jpg',
            // The price the shopper saw when they added it. Checked against the live
            // price at placement — and if it has moved, the shopper is told rather than
            // silently charged the new one.
            unitPrice: $line->unit_price_minor,
            quantity: $line->quantity,
            lineTotal: $line->unit_price_minor->timesQuantity($line->quantity),
            inStock: $product->isPurchasable(),
            stockAvailable: (int) $product->stock_quantity,
        );
    }
}
