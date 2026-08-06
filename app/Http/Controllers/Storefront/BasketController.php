<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Actions\AddProductToBasketAction;
use App\Domain\Ordering\Actions\PruneOrphanedBasketLinesAction;
use App\Domain\Ordering\Actions\UpdateBasketLineAction;
use App\Domain\Ordering\Http\Requests\AddToBasketRequest;
use App\Domain\Ordering\Http\Requests\UpdateBasketLineRequest;
use App\Domain\Ordering\Models\BasketLine;
use App\Domain\Ordering\Models\Coupon;
use App\Domain\Ordering\Queries\Internal\GetBasketSummaryQuery;
use App\Domain\Ordering\Services\AppliedCoupon;
use App\Domain\Ordering\Services\BasketResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BasketController
{
    public function __construct(
        private BasketResolver $baskets,
        private GetBasketSummaryQuery $summary,
        private AddProductToBasketAction $addAction,
        private UpdateBasketLineAction $updateAction,
        private PruneOrphanedBasketLinesAction $pruneOrphans,
        private AppliedCoupon $appliedCoupon,
    ) {}

    public function show(): View
    {
        $basket = $this->baskets->current();

        // Clear any line whose product has been deleted, and say so. Silently shrinking
        // someone's cart is worse than telling them.
        $pruned = $basket === null ? 0 : $this->pruneOrphans->execute($basket->id);

        if ($pruned > 0) {
            $basket = $this->baskets->current();
            session()->flash('error', $pruned === 1
                ? 'One item was removed from your cart because it is no longer sold.'
                : "{$pruned} items were removed from your cart because they are no longer sold.");
        }

        return view('shop.cart', [
            'basket' => $this->summary->execute($basket),
            'breadcrumbs' => ['Your Cart' => null],
        ]);
    }

    public function add(AddToBasketRequest $request): RedirectResponse
    {
        // Not a bare findOrFail: an inactive, unpublished or scheduled product used
        // to 404 on the storefront and still sell through this endpoint.
        $product = Product::query()->visible()->findOrFail($request->validated()['product_id']);

        try {
            $this->addAction->execute(
                $this->baskets->currentOrCreate(),
                $product,
                $request->quantity()
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('cart')
            ->with('status', "{$product->name} was added to your cart.");
    }

    /** "Add All To Cart" from the Frequently Bought Together block. */
    public function addMany(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:10'],
            'product_ids.*' => ['string', 'exists:products,id'],
        ]);

        $basket = $this->baskets->currentOrCreate();
        $added = 0;

        foreach (Product::query()->visible()->whereIn('id', $validated['product_ids'])->get() as $product) {
            try {
                $this->addAction->execute($basket, $product);
                $added++;
            } catch (RuntimeException) {
                // One unavailable companion part must not lose the rest of the basket.
                continue;
            }
        }

        return redirect()->route('cart')->with(
            $added > 0 ? 'status' : 'error',
            $added > 0
                ? "{$added} item(s) added to your cart."
                : 'None of those parts are available right now.'
        );
    }

    public function update(UpdateBasketLineRequest $request, string $line): RedirectResponse
    {
        $requested = $request->quantity();
        $set = $this->updateAction->execute($this->ownedLine($line), $requested);

        // Tell the shopper when they got less than they asked for. Quietly capping is
        // how someone discovers the shortfall at the till.
        if ($set > 0 && $set < $requested) {
            return redirect()->route('cart')->with(
                'error',
                "Only {$set} of that part are in stock, so your cart was set to {$set}."
            );
        }

        return redirect()->route('cart');
    }

    public function remove(string $line): RedirectResponse
    {
        $this->ownedLine($line)->delete();

        return redirect()->route('cart')->with('status', 'Item removed from your cart.');
    }

    /**
     * A line may only be touched through the basket it belongs to. Without this check
     * anyone could post another visitor's line id and edit their cart.
     */
    private function ownedLine(string $lineId): BasketLine
    {
        $basket = $this->baskets->current();

        if ($basket === null) {
            throw new NotFoundHttpException('No basket.');
        }

        // The product is loaded explicitly: the stock cap needs it, and relying on a
        // lazy load made the cap read stock as zero and clamp every quantity to 1.
        $line = BasketLine::query()
            ->with('product')
            ->where('basket_id', $basket->id)
            ->whereKey($lineId)
            ->first();

        if ($line === null) {
            throw new NotFoundHttpException('That item is not in your cart.');
        }

        return $line;
    }

    /**
     * Apply a discount code.
     *
     * A code below its minimum is still ACCEPTED and kept, with a message saying how much
     * more is needed — dropping it silently would leave a shopper who typed a valid code
     * staring at an unchanged total with no explanation.
     */
    public function applyCoupon(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10'],
        ]);

        $coupon = Coupon::findUsable($validated['code']);

        if ($coupon === null) {
            // Deliberately the same message whether the code never existed or has been turned
            // off: telling the difference lets somebody probe for retired codes.
            return back()->with('coupon_error', 'That code is not valid.');
        }

        $basket = $this->baskets->current();
        $summary = $this->summary->execute($basket);

        $this->appliedCoupon->apply($coupon);

        $reason = $coupon->reasonItCannotApply($summary->subtotal);

        return $reason === null
            ? back()->with('status', "{$coupon->code} applied — {$coupon->discount_percent}% off.")
            : back()->with('coupon_error', $reason);
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->appliedCoupon->clear();

        return back()->with('status', 'Discount code removed.');
    }
}
