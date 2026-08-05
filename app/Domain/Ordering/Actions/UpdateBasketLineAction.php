<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Models\BasketLine;
use Illuminate\Support\Facades\Log;

final class UpdateBasketLineAction
{
    /**
     * Sets a line's quantity, capped at what is actually on the shelf.
     *
     * A quantity of zero removes the line, which is what the theme's stepper implies.
     *
     * Capping rather than rejecting is deliberate: a shopper who asks for ten of
     * something with three left is better served by getting three and being told, than
     * by an error that loses their intent. What must never happen is the basket
     * carrying more than exists — that is how checkout drove stock to -498.
     *
     * @return int the quantity actually set, which may be lower than asked for
     */
    public function execute(BasketLine $line, int $quantity): int
    {
        if ($quantity < 1) {
            $line->delete();
            Log::info('ordering.update_basket_line.removed', ['line_id' => $line->id]);

            return 0;
        }

        $available = (int) ($line->product?->stock_quantity ?? 0);
        $capped = max(1, min($quantity, $available));

        $line->update(['quantity' => $capped]);

        Log::info('ordering.update_basket_line.success', [
            'line_id' => $line->id,
            'requested' => $quantity,
            'set' => $capped,
            'available' => $available,
        ]);

        return $capped;
    }
}
