{{--
    One product card, cut from the theme's own markup (best-sellers card 1) and made
    dynamic. Every class, attribute and svg path here is the theme's — the only edits
    are Blade echoes and the two loops that replaced repeated hardcoded markup.

    Every strip, listing and recommendation block renders through this file, so there
    is one place a card can be got wrong rather than six.

    @param  \App\Domain\Catalog\DTOs\ProductCardData  $product
    @param  string|null  $variant  wrapper classes; defaults to the slider variant
--}}
                                <div class="brator-product-single-item-area {{ $variant ?? 'splide__slide design-two' }}">
                                    <div class="brator-product-single-item-info info-content-flex">
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
                                        <div class="brator-product-single-item-btn"><form method="post" action="{{ route('cart.add', [], false) }}">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}" /><button type="submit" @disabled(! $product->inStock)>Add to cart</button></form></div>
                                    </div>
                                </div>
