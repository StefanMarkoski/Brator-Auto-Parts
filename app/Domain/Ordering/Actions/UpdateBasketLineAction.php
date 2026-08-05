<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Models\BasketLine;
use Illuminate\Support\Facades\Log;

final class UpdateBasketLineAction
{
    /** A quantity of zero removes the line, which is what the theme's stepper implies. */
    public function execute(BasketLine $line, int $quantity): void
    {
        if ($quantity < 1) {
            $line->delete();
            Log::info('ordering.update_basket_line.removed', ['line_id' => $line->id]);

            return;
        }

        $line->update(['quantity' => $quantity]);

        Log::info('ordering.update_basket_line.success', [
            'line_id' => $line->id,
            'quantity' => $quantity,
        ]);
    }
}
