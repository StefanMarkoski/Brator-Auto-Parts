<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Ordering\Actions\GenerateCouponAction;
use App\Domain\Ordering\Models\Coupon;
use App\Support\ValueObjects\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Discount codes.
 *
 * The code is GENERATED, never typed. Staff choose the percentage and, optionally, the
 * minimum spend; the readable ten-character code comes back from the action. Letting somebody
 * type their own would mean collisions, ambiguous characters and codes that cannot be read
 * down a phone.
 */
final class CouponController
{
    public function __construct(private GenerateCouponAction $generate) {}

    public function index(): View
    {
        return view('admin.pages.coupons', [
            // Which one the top bar is actually showing. Ticking three and seeing one is
            // confusing unless the screen says which.
            'promoted' => Coupon::promoted(),
            'coupons' => Coupon::query()
                ->orderByDesc('is_active')
                ->orderByDesc('created_at')
                // A tiebreaker, because created_at is not unique on a fast machine and two
                // coupons made in the same second would otherwise swap places per request.
                ->orderBy('id')
                ->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'discount_percent' => ['required', 'integer', 'min:1', 'max:100'],
            // The optional threshold, in major units as staff think of it. Nullable rather
            // than defaulting to 0: "no minimum" is the absence of a value, and 0 is a real
            // amount that would read as one.
            'minimum_order_major' => ['nullable', 'numeric', 'min:0'],
            'show_as_promotion' => ['nullable', 'boolean'],
        ]);

        $minimum = ($validated['minimum_order_major'] ?? null) === null
            ? null
            : Money::fromMajor((float) $validated['minimum_order_major']);

        // A minimum of zero is no minimum. Storing it would make describe() claim "off orders
        // over 0,00 ден", which is noise pretending to be a rule.
        if ($minimum !== null && $minimum->isZero()) {
            $minimum = null;
        }

        try {
            $coupon = $this->generate->execute((int) $validated['discount_percent'], $minimum);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.coupons.index')->with('error', $e->getMessage());
        }

        if ($request->boolean('show_as_promotion')) {
            $coupon->update(['show_as_promotion' => true]);
        }

        return redirect()
            ->route('admin.coupons.index')
            ->with('status', "{$coupon->code} created — {$coupon->describe()}."
                .($coupon->show_as_promotion
                    ? ' It is now the offer in the shop\'s top bar.'
                    : ''));
    }

    /**
     * Flip one switch — live, or promoted.
     *
     * Which one is decided by the field posted, so a row's two buttons cannot fight over the
     * other's value. Sending both from a single button was the alternative and it means every
     * click rewrites a setting somebody did not touch.
     */
    public function update(Request $request, string $coupon): RedirectResponse
    {
        $model = Coupon::query()->findOrFail($coupon);

        if ($request->has('show_as_promotion')) {
            $model->update(['show_as_promotion' => $request->boolean('show_as_promotion')]);

            return redirect()
                ->route('admin.coupons.index')
                ->with('status', $model->show_as_promotion
                    ? "{$model->code} is now the offer in the shop's top bar."
                    : "{$model->code} is no longer advertised. It still works if somebody types it.");
        }

        $model->update(['is_active' => $request->boolean('is_active')]);

        return redirect()
            ->route('admin.coupons.index')
            ->with('status', $model->is_active
                ? "{$model->code} is live again."
                : "{$model->code} is switched off. Baskets already holding it stop discounting now, "
                    .'and it is no longer advertised.');
    }

    public function destroy(string $coupon): RedirectResponse
    {
        $model = Coupon::query()->findOrFail($coupon);

        if ($model->times_used > 0) {
            /*
             | Refused once it has been used, and switched off instead.
             |
             | Receipts snapshot the code and the amount, so deleting it would not corrupt any
             | order — but it WOULD destroy the only record of which code produced those
             | discounts, and "why is this order 10% lighter" is a question somebody asks
             | months later. Deactivating answers it; deleting does not.
            */
            $model->update(['is_active' => false]);

            return redirect()
                ->route('admin.coupons.index')
                ->with('error', "{$model->code} has been used on {$model->times_used} order(s), so it "
                    .'was switched off rather than deleted — otherwise nothing would explain the '
                    .'discount on those receipts.');
        }

        $model->delete();

        return redirect()->route('admin.coupons.index')->with('status', "{$model->code} was deleted.");
    }
}
