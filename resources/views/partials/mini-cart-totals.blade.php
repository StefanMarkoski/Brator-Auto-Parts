{{--
    THE MINI-CART'S MONEY ROWS. THEY MUST ADD UP TO THE TOTAL BELOW THEM.

    They did not. The panel showed Subtotal and VAT and then a total larger than both
    together, because delivery sat inside the total with no row of its own: one air filter
    read 844,05 + 186,13 against a total of 1.220,18 — a gap of 190,00 with nothing on
    screen to account for it. The VAT figure also includes delivery VAT, so it was not 18%
    of the subtotal either (151,93 would be), and a shopper checking the arithmetic found
    two numbers wrong rather than one.

    With a coupon it was worse: `subtotal` is the PRE-discount figure, and with no discount
    row the panel quoted more than was actually being charged while saying nothing about
    the money coming off.

    Now the same rows as the cart page, in the same order, from the same BasketSummary:

        subtotal − discount + VAT + delivery = total

    exactly, at any basket value. It reconciled before only above the free-delivery
    threshold, which is how it survived every casual look.

    ONE PARTIAL, TWO HEADERS. The homepage and the shop pages use different headers from
    the purchased theme, and this block was duplicated in both — so the bug had to be found
    twice and fixed twice, and any future change silently applies to one header and not the
    other. Included by partials/header.blade.php and partials/header-shop.blade.php.

    No new CSS: brator-cart-item-money is the theme's own row class, already used by the
    two rows that were here before.
--}}
<div class="brator-cart-item-list-money-area">
    <div class="brator-cart-item-money"><span>Subtotal (excl. VAT)</span><span>{{ $miniCart->subtotal->format() }}</span></div>
    @if ($miniCart->hasDiscount())
        <div class="brator-cart-item-money"><span>Discount{{ $miniCart->coupon ? ' ('.$miniCart->coupon->code.')' : '' }}</span><span>−{{ $miniCart->discount->format() }}</span></div>
    @endif
    <div class="brator-cart-item-money"><span>VAT ({{ (int) config('shop.vat_rate') }}%)</span><span>{{ $miniCart->vat->format() }}</span></div>
    <div class="brator-cart-item-money"><span>Delivery</span><span>{{ $miniCart->shipping->isZero() ? 'Free' : $miniCart->shipping->format() }}</span></div>
</div>
