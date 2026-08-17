{{--
    One product card, cut from the theme's own markup (best-sellers card 1) and made
    dynamic. Every class, attribute and svg path here is the theme's — the only edits
    are Blade echoes and the two loops that replaced repeated hardcoded markup.

    Every strip, listing and recommendation block renders through this file, so there
    is one place a card can be got wrong rather than six.

    @param  \App\Domain\Catalog\DTOs\ProductCardData  $product
    @param  string|null  $variant  wrapper classes; defaults to the slider variant
    @param  array|null  $bundleCheckbox  turns the card into a bundle row: name, value, price,
            label, and optionally checked/disabled

    The parameter is NOT called $bundle, and that is not fussiness. @include leaks the including
    view's variables into every nested card, and the product page already has a $bundle collection
    — so a card in an unrelated strip on the same page inherited it and tried to read a Collection
    as this array. Every product page with a Recently Viewed or You May Also Like strip 500'd.
--}}
                                <div class="brator-product-single-item-area {{ $variant ?? 'splide__slide design-two' }}">
                                    {{--
                                        d-none in a bundle, which is what the theme's own bundle cards
                                        do. This row holds the badges, and on the current part it
                                        carried a "20% OFF" tag that made that one card 28px taller
                                        than its neighbours and started it 14px higher — so the row of
                                        three sat visibly ragged. A discount badge is also beside the
                                        point in a "tick these to add them" list.
                                    --}}
                                    <div class="brator-product-single-item-info @isset($bundleCheckbox)d-none @endisset info-content-flex">
                                        <div class="brator-product-single-item-info-left">
                                            @foreach ($product->badges as $badge)
                                                <div class="{{ $badge['class'] }}">{{ $badge['label'] }}</div>
                                            @endforeach
                                        </div>
                                        {{-- The wishlist heart is REMOVED from every card, for the same reason it went
                 from the header: there is no wishlist, so it was a dead link repeated once
                 per product — the single biggest source of unclickable controls in the shop. --}}
                                    </div>
                                    <div class="brator-product-single-item-img"><a href="{{ route('shop.product', $product->slug, false) }}"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $product->imagePath }}" alt="{{ $product->name }}" /></a></div>
                                    <div class="brator-product-single-item-mini">
                                        <div class="brator-product-single-item-cat"><a href="{{ route('shop.product', $product->slug, false) }}">{{ $product->brandName }}</a></div>
                                        <div class="brator-product-single-item-title">
                                            <h5><a href="{{ route('shop.product', $product->slug, false) }}">{{ $product->name }}</a></h5>
                                        </div>
                                        <div class="brator-product-single-item-review">
                                            <div class="brator-review">
                                                @for ($i = 1; $i <= 5; $i++)
                                                <svg class="{{ $i <= $product->stars ? 'active' : 'd-active' }}" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                @endfor
                                            </div>
                                            <div class="brator-review-text">
                                                <p>{{ $product->reviewsCount }} Reviews</p>
                                            </div>
                                        </div>
                                        <div class="brator-product-single-item-price">
                                            @if ($product->originalPrice)
                                                <p><sub>{{ $product->price->format() }}</sub><b class="pub">{{ $product->originalPrice->format() }}</b></p>
                                            @else
                                                <p class="brator-price-black-text"><sub>{{ $product->price->format() }}</sub></p>
                                            @endif
                                        </div>
                                        @isset($bundleCheckbox)
                                            {{--
                                                THE BUNDLE CHECKBOX, in the theme's own place: inside the
                                                card, straight after the price.

                                                It has to be here. The CSS that turns this input into the
                                                theme's box-and-tick, and draws the "+" between cards, is
                                                scoped to

                                                  .check-box-product .…item-area.design-two .…item-checkbox

                                                so a checkbox placed outside the card gets none of it. That is
                                                what this section looked like before: raw browser checkboxes
                                                with plain text labels, sitting beside cards they did not
                                                belong to, in a 2,175px-tall block.

                                                The theme gives the input no visible label, because the card
                                                names the product. An unlabelled checkbox is still wrong for a
                                                screen reader, so it carries an aria-label — an attribute, so
                                                nothing about the design changes.
                                            --}}
                                            <div class="brator-product-single-item-checkbox">
                                                <input type="checkbox" name="{{ $bundleCheckbox['name'] }}"
                                                    value="{{ $bundleCheckbox['value'] }}"
                                                    data-bundle-price="{{ $bundleCheckbox['price'] }}"
                                                    aria-label="{{ $bundleCheckbox['label'] }}"
                                                    @checked($bundleCheckbox['checked'] ?? true)
                                                    @disabled($bundleCheckbox['disabled'] ?? false) />
                                                <div class="arow-check-right"></div>
                                            </div>
                                        @else
                                            {{--
                                                WHY THIS BUTTON CARRIES button-fill-one AND AN INLINE STYLE.

                                                The theme paints this control with

                                                  .…item-area .…item-mini .brator-product-single-item-btn a

                                                — an ANCHOR. The theme's own card merely links to the product
                                                page; ours has to POST, so it is a <button> in a <form>, and that
                                                one element-name difference meant the selector never matched.
                                                Measured: the shop's Add to cart came out as a raw browser
                                                button — rgb(239,239,239) grey on black, 80x21px, outset border —
                                                sitting in a 44px slot, while every other button in the shop is
                                                #f73312 orange. Stefan's words: "no style at all".

                                                No new class, and theme-style.css is untouched. button-fill-one is
                                                the theme's OWN class: it is the first selector in the same
                                                grouped rule the anchor is in, so it is the identical orange fill.
                                                The inline declarations are that anchor rule's overrides
                                                (theme-style.css:4373) copied verbatim — they live behind a
                                                selector we cannot match, and there is no project stylesheet to
                                                put them in.

                                                display:block is the only value here that is not the theme's: an
                                                <a> is inline, where width:100% does nothing, so the theme never
                                                had to say it.

                                                Out of stock is dimmed and says so, rather than being an orange
                                                button that refuses to be pressed — the theme ships no disabled
                                                state for this control, so an undimmed one reads as broken.
                                            --}}
                                            <div class="brator-product-single-item-btn">
                                                <form method="post" action="{{ route('cart.add', [], false) }}" style="margin: 0" data-basket-form data-basket-add>
                                                    @csrf
                                                    <input type="hidden" name="product_id" value="{{ $product->id }}" />
                                                    <button type="submit" class="button-fill-one"
                                                        style="display: block; width: 100%; padding: 13px 20px; font-size: 14px; line-height: 14px; height: auto; text-align: center; font-weight: 500; overflow: hidden;@unless ($product->inStock) opacity: 0.55; cursor: not-allowed;@endunless"
                                                        @disabled(! $product->inStock)>{{ $product->inStock ? 'Add to cart' : 'Out of stock' }}</button>
                                                </form>
                                            </div>
                                        @endisset
                                    </div>
                                </div>
