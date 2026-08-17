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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BasketController
{
    /**
     * The one answer for "no such code" and "that code is switched off".
     *
     * A single constant rather than the sentence typed in three places, so the three ways of
     * failing a code lookup cannot drift apart — and drifting apart is exactly what would turn
     * this into a way to tell a retired code from a typo. CouponLiveCheckTest compares the whole
     * payloads to keep it that way.
     */
    private const COUPON_NOT_VALID = [
        'known' => false,
        'ok' => false,
        'message' => 'That code is not valid.',
    ];

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

        /*
         | THE PRICE IS IN THE SENTENCE NOW, and that is not decoration.
         |
         | Adding to the basket no longer leaves the page — storefront.js posts this in the
         | background and announces it in the mini-cart, rather than the whole shop jumping to
         | /cart. That was Stefan's call and he is right: being thrown onto the cart after every
         | single add is a shop arguing with the person browsing it. So this sentence is now the
         | entire confirmation, delivered on the page they are still standing on, and "what did
         | that cost me" is the obvious next question.
         |
         | Formatted through Money like every other figure, so it cannot disagree with the price
         | printed a few lines above the button. effectivePrice is the sale price when there is one.
         |
         | The redirect to /cart is untouched: that is the no-JavaScript path, and this flash is
         | what the cart renders at the top when it gets there.
        */
        return redirect()
            ->route('cart')
            ->with('status', "{$product->name} — {$product->effectivePrice->format()} added to your cart.");
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
            //
            // withInput() so the code the shopper typed is still in the field afterwards.
            // Without it old('code') came back empty and a single mistyped character meant
            // typing the whole thing again — measured, on a page that also reloaded and emptied
            // the five checkout fields.
            return back()->withInput()->with('coupon_error', self::COUPON_NOT_VALID['message']);
        }

        $basket = $this->baskets->current();
        $summary = $this->summary->execute($basket);

        $this->appliedCoupon->apply($coupon);

        $reason = $coupon->reasonItCannotApply($summary->subtotal);

        return $reason === null
            ? back()->with('status', "{$coupon->code} applied — {$coupon->discount_percent}% off.")
            : back()->with('coupon_error', $reason);
    }

    /**
     * Is this code usable? Answered while it is being typed, without applying anything.
     *
     * WHAT THIS MAY AND MAY NOT REVEAL — read before changing the shape of the response.
     *
     * `known` is true only for a code that exists AND is switched on, because it comes from
     * Coupon::findUsable(), whose scope filters on is_active. So a retired code answers exactly
     * as a typo does, with the same single sentence, and this endpoint cannot be used to discover
     * codes that have been turned off. That is the property applyCoupon() was written to protect
     * and it is preserved here deliberately, not by accident.
     *
     * THE CONDITION UNDER WHICH THIS IS SAFE AT ALL, recorded so it is not lost: every usable
     * code is ALREADY advertised. Coupon::advertised() returns every active coupon and the
     * storefront prints them in the top bar of the homepage, so the only thing this endpoint can
     * confirm is something a visitor can read off the front page. If the shop ever wants an
     * unadvertised or staff-only code, this check becomes a genuine discovery oracle and must be
     * dropped or reduced to something that never confirms — the throttle on the route would slow
     * that down, not prevent it.
     *
     * It applies nothing and writes nothing, so it is safe to call on a keystroke.
     */
    public function checkCoupon(Request $request): JsonResponse
    {
        /*
         | Validated by hand rather than with $request->validate(), because this endpoint must
         | answer in JSON no matter what. $request->validate() on a web route REDIRECTS on
         | failure — measured, a bare GET here answered 302 to the site root — and a fetch()
         | following a redirect to the homepage would hand the field a chunk of HTML to parse as
         | its verdict.
         |
         | The failure body is the SAME single sentence an unknown code gets. An eleven-character
         | string is not a code, and answering it differently would be a second channel telling
         | somebody what a code looks like.
        */
        $validator = Validator::make($request->query(), [
            'code' => ['required', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            return response()->json(self::COUPON_NOT_VALID, 422);
        }

        $coupon = Coupon::findUsable((string) $validator->validated()['code']);

        if ($coupon === null) {
            // The same sentence applyCoupon() uses, so the live field and the button never
            // disagree about the same code.
            return response()->json(self::COUPON_NOT_VALID);
        }

        $reason = $coupon->reasonItCannotApply($this->summary->execute($this->baskets->current())->subtotal);

        /*
         | A real code that has not reached its minimum spend is `known` but not `ok`: it is not
         | wrong, it is not ready. The wording is reasonItCannotApply()'s, which is what pressing
         | Apply would say — and applying it really would still be accepted and kept, so telling
         | the shopper it is invalid would be a lie that costs them the discount.
        */
        return response()->json([
            'known' => true,
            'ok' => $reason === null,
            'message' => $reason ?? $coupon->describe(),
        ]);
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->appliedCoupon->clear();

        return back()->with('status', 'Discount code removed.');
    }
}
