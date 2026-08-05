{{--
    The list-layout product card (the theme's design-three), cut from
    shop-sub-category-list.html. Structurally different from the grid card — image
    left, details middle, price and button right — so it is its own partial rather
    than a parameter on the grid one.

    @param  \App\Domain\Catalog\DTOs\ProductCardData  $product
--}}
                        <div class="brator-product-single-item-area design-three">
                            <div class="brator-product-single-item-area-left">
                                <div class="brator-product-single-item-info info-content-flex">
                                    <div class="brator-product-single-item-info-left">
                                        @foreach ($product->badges as $badge)
                                            <div class="{{ $badge['class'] }}">{{ $badge['label'] }}</div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="brator-product-single-item-img"><a href="{{ route('shop.product', $product->slug) }}"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $product->imagePath }}" alt="{{ $product->name }}" /></a></div>
                            </div>
                            <div class="brator-product-single-item-area-mdl">
                                <div class="brator-product-single-item-mini">
                                    <div class="brator-product-single-item-cat"><a href="{{ route('shop.product', $product->slug) }}">{{ $product->brandName }}</a></div>
                                    <div class="brator-product-single-item-title">
                                        <h5><a href="{{ route('shop.product', $product->slug) }}">{{ $product->name }}</a></h5>
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
                                    <div class="brator-product-single-item-featu">
                                        <div class="brator-product-single-item-featu-single">
                                            <p>Country:<span>Germany</span></p>
                                        </div>
                                        <div class="brator-product-single-item-featu-single">
                                            <p>Part Number:<span>WS5-451A2</span></p>
                                        </div>
                                        <div class="brator-product-single-item-featu-single">
                                            <p>Color:<span>White/Sliver</span></p>
                                        </div>
                                        <div class="brator-product-single-item-featu-single">
                                            <p>Material:<span>Metal & Chrome OEM: Replica 178</span></p>
                                        </div>
                                        <div class="brator-product-single-item-featu-single">
                                            <p>Chrome OEM:<span>Replica 178</span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="brator-product-single-item-area-right">
                                <div class="brator-product-single-item-price">
                                    @if ($product->originalPrice)<p><sub>{{ $product->price->format() }}</sub><b class="pub">{{ $product->originalPrice->format() }}</b></p>@else<p class="brator-price-black-text"><sub>{{ $product->price->format() }}</sub></p>@endif<span>Included Tax</span>
                                </div>
                                <div class="brator-product-single-item-btn">
                                    <div class="brator-product-single-item-btn-cart">
                                        <button>Add To Cart</button>
                                    </div>
                                    <div class="brator-product-single-cart-action">
                                        <div class="brator-product-single-cart-wish">
                                            <button>
                                                <svg class="bi bi-suit-heart-fill" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M4 1c2.21 0 4 1.755 4 3.92C8 2.755 9.79 1 12 1s4 1.755 4 3.92c0 3.263-3.234 4.414-7.608 9.608a.513.513 0 0 1-.784 0C3.234 9.334 0 8.183 0 4.92 0 2.755 1.79 1 4 1z"></path>
                                                </svg><span>Wishlist</span>
                                            </button>
                                        </div>
                                        <div class="brator-product-single-cart-compare">
                                            <button>
                                                <svg class="bi bi-arrow-left-right" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path fill-rule="evenodd" d="M1 11.5a.5.5 0 0 0 .5.5h11.793l-3.147 3.146a.5.5 0 0 0 .708.708l4-4a.5.5 0 0 0 0-.708l-4-4a.5.5 0 0 0-.708.708L13.293 11H1.5a.5.5 0 0 0-.5.5zm14-7a.5.5 0 0 1-.5.5H2.707l3.147 3.146a.5.5 0 1 1-.708.708l-4-4a.5.5 0 0 1 0-.708l4-4a.5.5 0 1 1 .708.708L2.707 4H14.5a.5.5 0 0 1 .5.5z"></path>
                                                </svg><span>Compare</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
