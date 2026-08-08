@extends('layouts.shop')

@section('title', 'Your Cart — Brator Auto Parts')

@section('content')
    <!-- bread start-->
    <div class="brator-breadcrumb-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="brator-breadcrumb">
                        <ul>
                            <li><a href="{{ route('home', [], false) }}">Home</a></li>
                            <li><a href="{{ route('shop.categories', [], false) }}">All Categories</a></li>
                            @foreach ($breadcrumbs ?? [] as $crumbLabel => $crumbUrl)
                                @if ($crumbUrl)
                                    <li><a href="{{ $crumbUrl }}">{{ $crumbLabel }}</a></li>
                                @else
                                    <li class="active-link">{{ $crumbLabel }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- bread end-->
    <div class="brator-cart-header-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-12">
                    <h3>Shopping Cart</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="brator-cart-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-xl-8 col-lg-12">
                    <div class="brator-cart-info">
                        <div class="brator-cart-h">
                            <h3>Your Cart</h3>
                        </div>
                        @if (session('status'))
                            <div class="brator-contact-form-field-info">
                                <p>{{ session('status') }}</p>
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="brator-contact-form-field-info">
                                <p>{{ session('error') }}</p>
                            </div>
                        @endif
                        <div class="brator-cart-list">
                            <div class="brator-cart-list-items title-me">
                                <div class="brator-cart-list-items-title">
                                    <h6>product</h6>
                                </div>
                                <div class="brator-cart-list-items-price">
                                    <h6>price</h6>
                                </div>
                                <div class="brator-cart-list-items-qty-area">
                                    <h6>qty</h6>
                                </div>
                                <div class="brator-cart-list-items-subtotal">
                                    <h6>subtotal</h6>
                                </div>
                                <div class="brator-cart-list-items-removed"></div>
                            </div>
                            @forelse ($basket->lines as $line)
                                <div class="brator-cart-list-items">
                                    <div class="brator-cart-list-items-title">
                                        <div class="img-cart"><a href="{{ route('shop.product', $line->productSlug, false) }}"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $line->imagePath }}" alt="{{ $line->productName }}" /></a></div>
                                        <div class="prodct-info">
                                            <h5><a href="{{ route('shop.product', $line->productSlug, false) }}">{{ $line->productName }}</a></h5>
                                            <p>{{ $line->brandName }} &middot; SKU {{ $line->productSku }}</p><a href="{{ route('shop.product', $line->productSlug, false) }}">Edit </a>
                                        </div>
                                    </div>
                                    <div class="brator-cart-list-items-price">
                                        <p><sup>{{ $line->unitPrice->format() }}</sup></p>
                                    </div>
                                    <div class="brator-cart-list-items-qty-area">
                                        <form method="post" action="{{ route('cart.update', $line->lineId, false) }}">
                                            @csrf
                                            {{-- The minus button never worked: all three controls were name="quantity",
                                                and a submit button's value loses to a same-named input. The buttons now post
                                                a separate `step`, applied to the input's value in the request object — so plus,
                                                minus and typing all work, and it still submits correctly without JavaScript. --}}
                                            <div class="brator-cart-list-items-qty">
                                                <button class="decrement-count-qty" type="submit" name="step" value="-1" aria-label="One fewer">-</button>
                                                <input type="number" name="quantity" value="{{ $line->quantity }}" min="0" max="99" />
                                                <button class="add-count-qty" type="submit" name="step" value="1" aria-label="One more">+</button>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="brator-cart-list-items-subtotal">
                                        <p>{{ $line->lineTotal->format() }}</p>
                                    </div>
                                    <div class="brator-cart-list-items-removed">
                                        <form method="post" action="{{ route('cart.remove', $line->lineId, false) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" aria-label="Remove {{ $line->productName }}">
                                                <svg class="bi bi-x" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <div class="brator-cart-list-items">
                                    <div class="brator-cart-list-items-title">
                                        <div class="prodct-info">
                                            <h5>Your cart is empty.</h5>
                                            <p>Browse the catalogue and add the parts you need.</p><a href="{{ route('shop.categories', [], false) }}">Shop all parts </a>
                                        </div>
                                    </div>
                                </div>
                            @endforelse
                        </div>

                        @if (! $basket->isEmpty())
                            <div class="brator-cart-list">
                                <div class="brator-cart-list-items title-me">
                                    <div class="brator-cart-list-items-title">
                                        <h6>order summary</h6>
                                    </div>
                                    <div class="brator-cart-list-items-subtotal">
                                        <h6>amount</h6>
                                    </div>
                                </div>
                                <div class="brator-cart-list-items">
                                    <div class="brator-cart-list-items-title">
                                        <div class="prodct-info">
                                            <h5>Subtotal</h5>
                                            <p>{{ $basket->itemCount }} item(s), excluding VAT</p>
                                        </div>
                                    </div>
                                    <div class="brator-cart-list-items-subtotal">
                                        <p>{{ $basket->subtotal->format() }}</p>
                                    </div>
                                </div>
                                @if ($basket->hasDiscount())
                                    {{-- Only when it actually discounts something. A zero row for a
                                         code that has not reached its minimum would read as broken. --}}
                                    <div class="brator-cart-list-items">
                                        <div class="brator-cart-list-items-title">
                                            <div class="prodct-info">
                                                <h5>Discount</h5>
                                                <p>{{ $basket->coupon->code }} — {{ $basket->coupon->discount_percent }}% off</p>
                                            </div>
                                        </div>
                                        <div class="brator-cart-list-items-subtotal">
                                            <p>−{{ $basket->discount->format() }}</p>
                                        </div>
                                    </div>
                                @endif
                                <div class="brator-cart-list-items">
                                    <div class="brator-cart-list-items-title">
                                        <div class="prodct-info">
                                            <h5>VAT ({{ (int) config('shop.vat_rate') }}%)</h5>
                                            {{-- VAT is charged on the DISCOUNTED goods, so say so
                                                 rather than leaving the shopper to check the sum. --}}
                                            <p>{{ $basket->hasDiscount() ? 'On '.$basket->discountedSubtotal()->format().' after discount' : 'Added at checkout' }}</p>
                                        </div>
                                    </div>
                                    <div class="brator-cart-list-items-subtotal">
                                        <p>{{ $basket->vat->format() }}</p>
                                    </div>
                                </div>
                                <div class="brator-cart-list-items">
                                    <div class="brator-cart-list-items-title">
                                        <div class="prodct-info">
                                            <h5>Delivery</h5>
                                            <p>{{ $basket->qualifiesForFreeShipping() ? 'Free on orders over '.\App\Domain\Ordering\DTOs\BasketSummary::freeDeliveryFrom()->format() : 'Flat rate' }}</p>
                                        </div>
                                    </div>
                                    <div class="brator-cart-list-items-subtotal">
                                        <p>{{ $basket->shipping->isZero() ? 'Free' : $basket->shipping->format() }}</p>
                                    </div>
                                </div>
                                <div class="brator-cart-list-items title-me">
                                    <div class="brator-cart-list-items-title">
                                        <h6>total to pay</h6>
                                    </div>
                                    <div class="brator-cart-list-items-subtotal">
                                        <h6>{{ $basket->total->format() }}</h6>
                                    </div>
                                </div>
                            </div>

                            <div class="brator-contact-form-area">
                                <div class="brator-contact-form">
                                    <div class="brator-contact-form-header">
                                        <h2>Checkout</h2>
                                        <p>No card is charged — this shop takes payment on delivery or by phone. You will get a receipt by email.</p>
                                    </div>
                                    {{-- session('error') is NOT repeated here. It is already
                                         rendered at the top of the cart, so a rejected checkout
                                         printed the same sentence twice on one page. --}}
                                    @if ($errors->any())
                                        <div class="brator-contact-form-field-info">
                                            @foreach ($errors->all() as $message)
                                                <p>{{ $message }}</p>
                                            @endforeach
                                        </div>
                                    @endif
                                    {{-- data-submit-once is the hook storefront.js binds the
                                         double-submit guard to: a shopper who double-clicks
                                         "Place order" would otherwise post /checkout twice, and
                                         nothing on the server side stops that becoming two
                                         receipts, two stock decrements and two emails. The
                                         action and method are untouched, so with JavaScript off
                                         this is the same plain form it was. --}}
                                    <form method="post" action="{{ route('checkout.place', [], false) }}" data-submit-once data-submit-once-label="Placing your order…">
                                        @csrf
                                        <div class="brator-contact-form-fields">
                                            <div class="brator-contact-form-field-two-items">
                                                <div class="brator-contact-form-field">
                                                    <input type="text" name="customer_name" value="{{ old('customer_name') }}" placeholder="Full name *" required />
                                                </div>
                                                <div class="brator-contact-form-field">
                                                    <input type="email" name="customer_email" value="{{ old('customer_email') }}" placeholder="Email *" required />
                                                </div>
                                            </div>
                                            <div class="brator-contact-form-field">
                                                <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="Phone (optional)" />
                                            </div>
                                            <div class="brator-contact-form-field">
                                                <textarea name="shipping_address" placeholder="Delivery address *" required>{{ old('shipping_address') }}</textarea>
                                            </div>
                                            <div class="brator-contact-form-field">
                                                <textarea name="notes" placeholder="Anything we should know? (optional)">{{ old('notes') }}</textarea>
                                            </div>
                                            <button type="submit">Place order &mdash; {{ $basket->total->format() }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif

                        <div class="brator-cart-checkout">
                            <div class="brator-cart-checkout-left">
                                {{--
                                    The theme shipped this disabled, reading "coupon codes coming
                                    soon". It is real now, in the theme's own markup — same classes,
                                    same place — so nothing is restyled.
                                --}}
                                @if ($basket->coupon !== null)
                                    <div class="brator-cart-checkout-fields">
                                        <input type="text" value="{{ $basket->coupon->code }}" readonly />
                                        <button type="submit" form="remove-coupon">Remove</button>
                                    </div>
                                    <p>{{ $basket->coupon->describe() }}@unless ($basket->hasDiscount()) — not applied yet @endunless</p>
                                @else
                                    <form method="post" action="{{ route('cart.coupon.apply', [], false) }}" class="brator-cart-checkout-fields">
                                        @csrf
                                        <input type="text" name="code" value="{{ old('code') }}" placeholder="Discount code" maxlength="10" required />
                                        <button type="submit">Apply Coupon</button>
                                    </form>
                                @endif
                                @if (session('coupon_error'))
                                    <p>{{ session('coupon_error') }}</p>
                                @endif
                            </div>
                            <div class="brator-cart-checkout-right">
                                <div class="brator-cart-checkout-back"><a href="{{ route('shop.categories', [], false) }}"> Continue Shopping</a></div>
                            </div>
                        </div>
                    </div>
                    {{--
                        These four closes were missing.

                        The page opened .brator-cart-area, its container, the .row and the
                        .col-xl-8 and closed none of them, and layouts/shop.blade.php yields the
                        content immediately before the footer — so the entire footer and the
                        scroll-to-top button were parsed INSIDE a two-thirds-width grid column,
                        inside a second container's padding. On the checkout page. The browser
                        recovered enough to render, which is exactly why it survived: nothing
                        errored, the footer was just visibly squashed and left-aligned.
                    --}}
                </div>{{-- .col-xl-8 --}}
            </div>{{-- .row --}}
        </div>{{-- .container --}}
    </div>{{-- .brator-cart-area --}}

    @if ($basket->coupon !== null)
        {{-- Outside every other form: a nested <form> is invalid HTML, the browser drops the inner
             one, and that is how the clear-filters button ended up submitting the wrong form. --}}
        <form method="post" id="remove-coupon" action="{{ route('cart.coupon.remove', [], false) }}" hidden>
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection
