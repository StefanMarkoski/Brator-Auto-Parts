{{--
    The header's mini-cart. The theme shipped two hardcoded wheels here, complete with a
    "Discount - $5.00" line for a discount feature that does not exist — so the panel
    told every visitor they had four wheels in a basket they had never touched.

    Built from the theme's own classes; the edit link goes to the product and the close
    button removes the line for real.
--}}
@forelse ($miniCart->lines as $line)
    <div class="brator-cart-item-list-item">
        <div class="brator-cart-item-list-item-img">
            <a href="{{ route('shop.product', $line->productSlug, false) }}"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="{{ \App\Support\ImageUrl::for($line->imagePath) }}" alt="{{ $line->productName }}" /></a>
        </div>
        <div class="brator-cart-item-list-item-title">
            <div class="brator-cart-item-list-item-title-one">
                <a href="{{ route('shop.product', $line->productSlug, false) }}">
                    <h2>{{ $line->productName }}</h2>
                </a>
                {{--
                    white-space: nowrap, AND WHY IT IS LOAD-BEARING RATHER THAN TIDYING.

                    The theme gives this row a FIXED 17px height and centres its contents. Its own
                    demo prices were "$25.00" beside product names like "Wheel", so nothing ever
                    wrapped. Ours are "6.312,79 ден" beside "Monroe Control Arm Bush Heavy Duty" —
                    the name takes two lines, the price column gets squeezed, and the price broke
                    into "6.312,79" / "ден". Two lines of text centred in a 17px box overflow it
                    equally in both directions, so the first line rendered ABOVE the row: every
                    price in the panel appeared to belong to the line above it. Screenshotted at
                    eight lines, which is when it becomes obvious.

                    Kept on one line, the flex row squeezes the NAME instead, which wraps properly
                    because it is a heading with no height fixed on it. An inline declaration: no
                    new class, and theme-style.css is untouched.
                --}}
                <div class="price-pdo" style="white-space: nowrap">
                    <h4>{{ $line->quantity }}</h4>
                    <h5>x</h5>
                    {{--
                        WHY THERE IS AN EMPTY SECOND SPAN, and it is not a stray.

                        The theme styles this pair as "current price, old price":

                          .price-pdo h6 span            { color: #ff3300 }
                          .price-pdo h6 span:last-child { color: #999; text-decoration: line-through }

                        With ONE span, that span is also the last child — so every unit price in
                        the mini-cart rendered grey and STRUCK THROUGH, reading as a price that no
                        longer applies. Spotted in a screenshot while verifying the scroll region;
                        it had been wrong since the day the panel was made real.

                        A basket line has no "before" price to show — BasketLineSummary carries one
                        Money, the price the line was added at — so the second span is empty. It
                        takes the strikethrough, is invisible with nothing in it, and the real price
                        stops being :last-child, which is the whole point. Two spans is the theme's
                        own contract for this element; the alternative was a rule of our own
                        overriding a purchased stylesheet.
                    --}}
                    <h6><span>{{ $line->unitPrice->format() }}</span><span></span></h6>
                </div>
            </div>
            <div class="brator-cart-item-list-item-title-accetion">
                <p>{{ $line->brandName }} &middot; SKU {{ $line->productSku }}</p>
                <div class="brator-cart-item-list-item-title-accetion-part">
                    <a href="{{ route('shop.product', $line->productSlug, false) }}">
                        <svg class="bi bi-pencil-square" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"></path>
                            <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"></path>
                        </svg></a>
                    {{-- data-basket-form: storefront.js posts this in the background and refreshes
                         the panel in place, so removing a line from the mini-cart no longer throws
                         the shopper onto /cart from whatever page they were browsing. Still a real
                         form with a real DELETE, so it works with JavaScript off. --}}
                    <form method="post" action="{{ route('cart.remove', $line->lineId, false) }}" data-basket-form>
                        @csrf
                        @method('DELETE')
                        <button class="cart-item-close" type="submit" aria-label="Remove {{ $line->productName }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="brator-cart-item-list-item">
        <div class="brator-cart-item-list-item-title">
            <div class="brator-cart-item-list-item-title-one">
                <h2>Your cart is empty</h2>
            </div>
        </div>
    </div>
@endforelse
