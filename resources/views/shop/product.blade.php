@extends('layouts.shop')

@section('title', 'Brator Auto Parts')

@section('content')
    <!-- bread start-->
    <div class="brator-breadcrumb-area gray-bg">
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
    <div class="brator-product-header-layout-area desing-one">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-md-12">
                    <div class="brator-product-header-layout">
                        <div class="brator-product-header-layout-img">
                            <div id="tabs-product-img" class="brator-product-img-tab-list js-tabs design-two">
                                <div class="brator-product-img-tab-header js-tabs__header">
                                    <ul>
                                        <li><a class="js-tabs__title" href="#" style="background-image:url(./assets/images//product-tab-img-01.jpeg)"></a></li>
                                        <li><a class="js-tabs__title" href="#" style="background-image:url(./assets/images//product-tab-img-02.jpeg)"></a></li>
                                        <li><a class="js-tabs__title" href="#" style="background-image:url(./assets/images//product-tab-img-03.jpeg)"></a></li>
                                        <li><a class="js-tabs__title" href="#" style="background-image:url(./assets/images//product-tab-img-04.jpeg)"></a></li>
                                    </ul>
                                </div>
                                <div class="js-tabs__content brator-product-img-tab-item"><img data-action="zoom" class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $product->images[0]->path ?? 'assets/images/product-tab-img-01.jpeg' }}" alt="{{ $product->name }}" />
                                    <p>click image to zoom in</p>
                                </div>
                                <div class="js-tabs__content brator-product-img-tab-item"><img data-action="zoom" class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $product->images[1]->path ?? 'assets/images/product-tab-img-02.jpeg' }}" alt="{{ $product->name }}" />
                                    <p>click image to zoom in</p>
                                </div>
                                <div class="js-tabs__content brator-product-img-tab-item"><img data-action="zoom" class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $product->images[2]->path ?? 'assets/images/product-tab-img-03.jpeg' }}" alt="{{ $product->name }}" />
                                    <p>click image to zoom in</p>
                                </div>
                                <div class="js-tabs__content brator-product-img-tab-item"><img data-action="zoom" class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $product->images[3]->path ?? 'assets/images/product-tab-img-04.jpeg' }}" alt="{{ $product->name }}" />
                                    <p>click image to zoom in</p>
                                </div>
                            </div>
                        </div>
                        <div class="brator-product-layout-header-content">
                            <div class="brator-product-hero-content">
                                <div class="brator-product-hero-content-info">
                                    <div class="brator-product-hero-content-brand"><a href="#_">Sparegold</a></div>
                                    <div class="brator-product-hero-content-brand-img"><a href="#_"> <img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $product->brand?->logo_path ?? 'assets/images/b-p-01.jpg' }}" alt="{{ $product->brand?->name }}" /></a></div>
                                    <div class="brator-product-hero-content-title">
                                        <h2>{{ $product->name }}</h2>
                                    </div>
                                    <div class="brator-product-hero-content-review">
                                        <div class="product-batch yollow-batch">new</div>
                                        <div class="brator-review-product">
                                            <div class="brator-review">
                                                @for ($i = 1; $i <= 5; $i++)
                                                <svg class="{{ $i <= round($product->rating_avg) ? 'active' : 'd-active' }}" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                @endfor
                                            </div>
                                            <div class="brator-review-text">
                                                <p>{{ $product->reviews_count }} Reviews</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="brator-product-hero-content-price">
                                        <h6>{{ ($product->sale_price_minor ?? $product->price_minor)->format() }}@if ($product->sale_price_minor) <del>{{ $product->price_minor->format() }}</del>@endif</h6>
                                    </div>
                                    <div class="brator-product-hero-content-stock">
                                        <h6>{{ $product->stock_status->label() }}</h6>
                                        <h5>
                                            <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                            </svg><span>This product fit for your vehicle</span>
                                        </h5>
                                    </div>
                                </div>
                                <div class="brator-product-hero-content-add-to-cart">
                                    @php($pickable = $product->attributeValues->filter(fn ($v) => $v->attribute->is_filterable && $v->value_string)->take(2))
                                    @foreach ($pickable as $pick)
                                        <div class="brator-product-single-cart-select">
                                            <p>{{ $pick->attribute->label }}</p>
                                            <select name="attr_{{ $pick->attribute->code }}" disabled>
                                                <option value="{{ $pick->value_string }}">{{ $pick->value_string }}</option>
                                            </select>
                                        </div>
                                    @endforeach
                                    <div class="brator-product-single-cart-sub-total">
                                        <p><span>Subtotal:</span> {{ ($product->sale_price_minor ?? $product->price_minor)->format() }}</p>
                                    </div>
                                    <form method="post" action="{{ route('cart.add', [], false) }}">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $product->id }}" />
                                        <div class="brator-product-single-cart-count-add">
                                            <div class="brator-product-single-cart-count">
                                                <div class="brator-brator-cart-list-items-qty">
                                                    <button class="decrement-count-qty" type="button">-</button>
                                                    <input type="number" name="quantity" value="1" min="1" max="99" />
                                                    <button class="add-count-qty" type="button">+</button>
                                                </div>
                                            </div>
                                            <div class="brator-product-single-cart-add">
                                                <button type="submit" @disabled(! $product->stock_status->isBuyable())>Add To Cart</button>
                                            </div>
                                        </div>
                                    </form>
                                    <div class="brator-product-single-cart-action">
                                        <div class="brator-product-single-cart-wish">
                                            <button>
                                                <svg class="bi bi-suit-heart-fill" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M4 1c2.21 0 4 1.755 4 3.92C8 2.755 9.79 1 12 1s4 1.755 4 3.92c0 3.263-3.234 4.414-7.608 9.608a.513.513 0 0 1-.784 0C3.234 9.334 0 8.183 0 4.92 0 2.755 1.79 1 4 1z"></path>
                                                </svg><span>Add to Wishlist</span>
                                            </button>
                                        </div>
                                        <div class="brator-product-single-cart-compare">
                                            <button>
                                                <svg class="bi bi-arrow-left-right" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path fill-rule="evenodd" d="M1 11.5a.5.5 0 0 0 .5.5h11.793l-3.147 3.146a.5.5 0 0 0 .708.708l4-4a.5.5 0 0 0 0-.708l-4-4a.5.5 0 0 0-.708.708L13.293 11H1.5a.5.5 0 0 0-.5.5zm14-7a.5.5 0 0 1-.5.5H2.707l3.147 3.146a.5.5 0 1 1-.708.708l-4-4a.5.5 0 0 1 0-.708l4-4a.5.5 0 1 1 .708.708L2.707 4H14.5a.5.5 0 0 1 .5.5z"></path>
                                                </svg><span>Add to Compare</span>
                                            </button>
                                        </div>
                                        <div class="brator-product-single-cart-share">
                                            <button>
                                                <svg id="lni_lni-share" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64" xml:space="preserve">
                                                    <g></g>
                                                    <path d="M29.4,41.7c1,0,1.8-0.8,1.8-1.8V23.8c0-6.1,5-11.1,11.1-11.1h14.6l-6.4,4.8c-0.8,0.6-0.9,1.7-0.4,2.4                        c0.3,0.5,0.9,0.7,1.4,0.7c0.4,0,0.7-0.1,1-0.3l8.6-6.5c1-0.7,1.6-1.8,1.5-2.9c0-1.1-0.6-2.2-1.6-2.9l-8.6-6.3                        C51.8,1,50.7,1.2,50.1,2c-0.6,0.8-0.4,1.9,0.4,2.4L57,9.2H42.3c-8.1,0-14.6,6.6-14.6,14.6v16.1C27.6,40.9,28.4,41.7,29.4,41.7z"></path>
                                                    <path d="M61,38.2c-1,0-1.8,0.8-1.8,1.8v15.5c0,2.1-1.7,3.8-3.8,3.8H8.6c-2.1,0-3.8-1.7-3.8-3.8V39.9c0-1-0.8-1.8-1.8-1.8                        s-1.8,0.8-1.8,1.8v15.5c0,4,3.3,7.3,7.3,7.3h46.8c4,0,7.3-3.3,7.3-7.3V39.9C62.8,38.9,62,38.2,61,38.2z"></path>
                                                </svg><span>Share</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="brator-product-single-light-info-area">
                                    <div class="brator-product-single-light-info-share">
                                        <svg id="lni_lni-map-marker" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64" xml:space="preserve">
                                            <g>
                                                <path d="M32,1.3c-13.9,0-25.3,11-25.3,24.4c0,10.7,15.3,28.4,21.9,35.6c0.9,1,2.1,1.5,3.4,1.5c1.3,0,2.5-0.5,3.4-1.5                    c6.6-7.1,21.9-24.9,21.9-35.6C57.3,12.2,45.9,1.3,32,1.3z M32.8,58.9c-0.5,0.5-1.2,0.5-1.6,0c-4.9-5.3-21-23.5-21-33.2                    C10.2,14.1,20,4.8,32,4.8s21.8,9.4,21.8,20.9C53.8,35.4,37.7,53.5,32.8,58.9z"></path>
                                                <path d="M32,15.7c-5.9,0-10.8,4.8-10.8,10.8c0,5.9,4.8,10.8,10.8,10.8s10.8-4.8,10.8-10.8C42.8,20.5,37.9,15.7,32,15.7z M32,33.7                    c-4,0-7.3-3.3-7.3-7.3c0-4,3.3-7.3,7.3-7.3c4,0,7.3,3.3,7.3,7.3C39.3,30.4,36,33.7,32,33.7z"></path>
                                            </g>
                                        </svg>
                                        <p><span>Ship to</span> North Hills, CA 91343</p>
                                    </div>
                                    <div class="brator-product-single-light-info">
                                        <div class="brator-product-single-light-info-s cat">
                                            <h5>Categories: </h5>@foreach ($product->categories as $category)<a href="{{ route('shop.category', $category->slug, false) }}">{{ $category->name }}</a>@endforeach
                                        </div>
                                        <div class="brator-product-single-light-info-s">
                                            <h5>Part Number: </h5>@foreach ($product->crossReferences->take(3) as $ref)<a href="#_">{{ $ref->number }}</a>@endforeach
                                        </div>
                                        <div class="brator-product-single-light-info-s">
                                            <h5>Tags:</h5><a href="#_">wheels</a><a href="#_">tires</a><a href="#_">rims</a><a href="#_">sliver</a><a href="#_">mercedes</a><a href="#_">glc</a>
                                        </div>
                                        <div class="brator-product-single-light-info-s">
                                            <h5>SKU:</h5><span>{{ $product->sku }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="brator-product-frequently-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-xxl-9 col-xl-12">
                    <div class="brator-product-single-frequently">
                        <h2>Frequently Bought Together</h2>
                        {{-- The theme's combined total was a fixed $409.27. It now sums the
                             ticked items — the current part plus whichever companions are
                             checked — and posts them all to the basket in one go. --}}
                        <form method="post" action="{{ route('cart.add-many', [], false) }}"
                            class="brator-product-single-frequently-list"
                            data-bundle data-currency="{{ config('shop.currency_symbol') }}">
                            @csrf
                            <div class="product-list-items check-box-product">
                                <label class="brator-product-single-item-checkbox">
                                    <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" checked data-bundle-price="{{ ($product->sale_price_minor ?? $product->price_minor)->toPrimitive() }}" />
                                    <span>This item: {{ $product->name }}</span>
                                </label>
                                @foreach ($boughtTogether as $related)
                                    <label class="brator-product-single-item-checkbox">
                                        <input type="checkbox" name="product_ids[]" value="{{ $related->id }}" checked data-bundle-price="{{ $related->price->toPrimitive() }}"
                                            @disabled(! $related->inStock) />
                                        <span>{{ $related->name }}</span>
                                    </label>
                                    @include('partials.product-card', ['product' => $related, 'variant' => 'design-two'])
                                @endforeach
                            </div>
                            <div class="brator-product-single-frequently-total">
                                <h6>Total:</h6><span data-bundle-total>{{ $boughtTogetherTotal->format() }}</span>
                                <button type="submit">Add All To Cart</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-xxl-3 col-xl-12">
                    <div class="brator-product-single-posts">
                        <h2>Guide & Blog</h2>
                        <div class="brator-blog-post-sidebar-items">
                            <div class="brator-blog-listing-single-item-area list-type-one">
                                <div class="type-post">
                                    <div class="brator-blog-listing-single-item-thumbnail"><a class="blog-listing-single-item-thumbnail-link" href="#_" aria-hidden="true" tabindex="-1"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/blog/blog-01.jpg" alt="blog-post-blog-01.jpg" /></a></div>
                                    <div class="brator-blog-listing-single-item-content">
                                        <h3 class="brator-blog-listing-single-item-title"><a href="#_">Replace Brakes Guide</a></h3>
                                        <div class="brator-blog-listing-single-item-excerpt">
                                            <p>The braking system of a vehicle is an important safety [...]</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="brator-blog-listing-single-item-area list-type-one">
                                <div class="type-post">
                                    <div class="brator-blog-listing-single-item-thumbnail"><a class="blog-listing-single-item-thumbnail-link" href="#_" aria-hidden="true" tabindex="-1"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/blog/blog-05.jpg" alt="blog-post-blog-05.jpg" /></a></div>
                                    <div class="brator-blog-listing-single-item-content">
                                        <h3 class="brator-blog-listing-single-item-title"><a href="#_">Things to keep in mind when washing a car</a></h3>
                                        <div class="brator-blog-listing-single-item-excerpt">
                                            <p>The braking system of a vehicle is an important safety [...]</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="brator-blog-listing-single-item-area list-type-one">
                                <div class="type-post">
                                    <div class="brator-blog-listing-single-item-thumbnail"><a class="blog-listing-single-item-thumbnail-link" href="#_" aria-hidden="true" tabindex="-1"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/blog/blog-06.jpg" alt="blog-post-blog-06.jpg" /></a></div>
                                    <div class="brator-blog-listing-single-item-content">
                                        <h3 class="brator-blog-listing-single-item-title"><a href="#_">Replace Rims by yourself,why not? All tools need to prepare</a></h3>
                                        <div class="brator-blog-listing-single-item-excerpt">
                                            <p>The braking system of a vehicle is an important safety [...]</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="brator-blog-listing-single-item-area list-type-one">
                                <div class="type-post">
                                    <div class="brator-blog-listing-single-item-thumbnail"><a class="blog-listing-single-item-thumbnail-link" href="#_" aria-hidden="true" tabindex="-1"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/blog/blog-07.jpg" alt="blog-post-blog-07.jpg" /></a></div>
                                    <div class="brator-blog-listing-single-item-content">
                                        <h3 class="brator-blog-listing-single-item-title"><a href="#_">Transmission for old car</a></h3>
                                        <div class="brator-blog-listing-single-item-excerpt">
                                            <p>The braking system of a vehicle is an important safety [...]</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="brator-product-single-tab-area design-one-m">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-xxl-8 col-md-12">
                    <div class="brator-product-single-tab-list js-tabs" id="tabs-product-content">
                        <div class="brator-product-single-tab-header js-tabs__header">
                            <ul>
                                <li><a class="js-tabs__title" href="#">Description </a></li>
                                <li><a class="js-tabs__title" href="#">Specification </a></li>
                                <li><a class="js-tabs__title" href="#">Reviews (14) </a></li>
                                <li><a class="js-tabs__title" href="#">Product Q&A</a></li>
                            </ul>
                        </div>
                        <div class="js-tabs__content brator-product-single-tab-item">
                            {!! $product->description ?: e($product->short_description) !!}<img src="/assets/images/product-tab.jpg" alt="alt" />
                            <h6>featured</h6>
                            <ul>
                                <li>Plastic Hub Centering Ring Ensures a Vibration Free Ride</li>
                                <li>Tight Runout Tolerances Ensure thatwheels are Straight, Round and have Even Thickness</li>
                                <li>Factory Balancing ofwheels to Minimize Vibrations and Need forwheel Weights</li>
                                <li>Load Rating Specified on Everywheel</li>
                                <li>Compatible with All Original Equipment Tire Pressure Monitoring System (TPMS) Sensors</li>
                                <li>Correct Fitment for Your Vehicle</li>
                                <li>Precise and Correctwheel Offset for Your Vehicle</li>
                                <li>Metal Decorative Rivets and Extra Thick Emblems Ensure Lasting Good Looks</li>
                                <li>TSW provides a five-year structural warranty</li>
                                <li>2-Year Warranty on Chrome and Silver Finish</li>
                            </ul>
                            <h6>Warranty</h6>
                            <p>With regular care and regular road conditions, SG offers a two-year finish warranty on itswheels with chrome and painted finishes. SG provides a five-year structural warranty forwheels it manufactures that are structurally unsound because of a manufacturing defect caused by SG that makes the wheel unfit for its ordinary purpose. Damage or issues withwheels manufactured by SG that are not caused by, or the result of, a manufacturing defect by SG are not covered under the warranty.</p>
                        </div>
                        <div class="js-tabs__content brator-product-single-tab-item">
                            <div class="specification-product-area">
                                @php($specs = collect([
                                        'Brand' => $product->brand?->name,
                                        'SKU' => $product->sku,
                                        'Condition' => $product->condition->label(),
                                        'Weight' => $product->weight_grams ? number_format($product->weight_grams).' g' : null,
                                    ])->merge(
                                        $product->attributeValues->mapWithKeys(fn ($value) => [
                                            $value->attribute->label.($value->attribute->unit ? ' ('.$value->attribute->unit.')' : '')
                                                => $value->value_string ?? ($value->value_number === null ? null : rtrim(rtrim(number_format((float) $value->value_number, 2, '.', ''), '0'), '.')),
                                        ])
                                    )->filter(fn ($v) => $v !== null && $v !== ''))

                                @foreach ($specs as $specLabel => $specValue)
                                    <div class="specification-product-item">
                                        <div class="specification-product-item-left">
                                            <p>{{ $specLabel }}</p>
                                        </div>
                                        <div class="specification-product-item-right header-light">
                                            <p>{{ $specValue }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="js-tabs__content brator-product-single-tab-item">
                            <div class="brator-review-comment-area">
                                <div class="brator-review-comment">
                                    <div class="brator-review-pint-count">
                                        <h6>4.5/5 </h6>
                                    </div>
                                    <div class="brator-review-comment-count">
                                        <div class="brator-review">
                                            <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                            </svg>
                                            <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                            </svg>
                                            <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                            </svg>
                                            <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                            </svg>
                                            <svg class="d-active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                            </svg>
                                        </div>
                                        <div class="brator-review-text">
                                            <p>14 Reviews</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="brator-review-comment-list">
                                    <div class="brator-review-comment-single-item">
                                        <div class="brator-review-comment-single-img"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/profile-01.jpg" alt="logo" /></div>
                                        <div class="brator-review-comment-single-content">
                                            <div class="brator-review">
                                                <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                <svg class="d-active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                            </div>
                                            <div class="brator-review-comment-single-title">
                                                <h6>Quality product &amp; very comfortable!</h6>
                                                <p>Location,fantastic. Accommodation, fantastic. Host, fantastic. If you have a chance to book this beautiful cottage do not hesitate.You will be glad you did. Thank you alison for a great stay and we will definitely be returning. Dave and sue.</p>
                                            </div>
                                            <div class="brator-review-comment-date">
                                                <h6><a href="#_">Paulo Dybala </a>on 25 April, 2022 </h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="brator-review-comment-single-item">
                                        <div class="brator-review-comment-single-img"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/profile-02.jpg" alt="logo" /></div>
                                        <div class="brator-review-comment-single-content">
                                            <div class="brator-review">
                                                <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                <svg class="d-active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                            </div>
                                            <div class="brator-review-comment-single-title">
                                                <h6>Awesome product</h6>
                                                <p>Location,fantastic. Accommodation, fantastic. Host, fantastic. If you have a chance to book this beautiful cottage do not hesitate.You will be glad you did. Thank you alison for a great stay and we will definitely be returning. Dave and sue.</p>
                                            </div>
                                            <div class="brator-review-comment-date">
                                                <h6><a href="#_">Paulo Dybala </a>on 25 April, 2022 </h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="brator-review-comment-single-item">
                                        <div class="brator-review-comment-single-img"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/profile-03.jpg" alt="logo" /></div>
                                        <div class="brator-review-comment-single-content">
                                            <div class="brator-review">
                                                <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                                <svg class="d-active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                            </div>
                                            <div class="brator-review-comment-single-title">
                                                <h6>Fast delivery &amp; quality product</h6>
                                                <p>Location,fantastic. Accommodation, fantastic. Host, fantastic. If you have a chance to book this beautiful cottage do not hesitate.You will be glad you did. Thank you alison for a great stay and we will definitely be returning. Dave and sue.</p>
                                            </div>
                                            <div class="brator-review-comment-date">
                                                <h6><a href="#_">Paulo Dybala </a>on 25 April, 2022 </h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="brator-contact-form-area product-review-form">
                                <div class="brator-contact-form-header">
                                    <h2>Write Your Review</h2>
                                </div>
                                <div class="brator-contact-form"><span class="info-text">Your email address will not be b.published. Required fields are marked *</span></div>
                                <div class="product-review-tag">
                                    <p>Your Rating</p>
                                    <div class="brator-review">
                                        <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                            <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                        </svg>
                                        <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                            <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                        </svg>
                                        <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                            <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                        </svg>
                                        <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                            <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                        </svg>
                                        <svg class="d-active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                            <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="brator-contact-form-fields">
                                <div class="brator-contact-form-field">
                                    <input type="text" name="sub" placeholder="Give your review a tittle (Optional)" />
                                </div>
                                <div class="brator-contact-form-field">
                                    <textarea name="sms" placeholder="Write your review here"></textarea>
                                </div>
                                <div class="brator-contact-form-field-two-items">
                                    <div class="brator-contact-form-field">
                                        <input type="text" name="name" placeholder="Your Email *" />
                                    </div>
                                    <div class="brator-contact-form-field">
                                        <input type="text" name="name" placeholder="Name" />
                                    </div>
                                </div>
                                <div class="brator-contact-form-field-info">
                                    <input type="checkbox" name="condcion" /><span>Save my name & email in this browser for next time i comment</span>
                                </div>
                                <div class="brator-contact-form-field">
                                    <button type="submit">Submit Review</button>
                                </div>
                            </div>
                        </div>
                        <div class="js-tabs__content brator-product-single-tab-item">
                            <p>pug</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- bread start-->
    <div class="brator-deal-product-slider recently-view">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-12">
                    <div class="brator-section-header">
                        <div class="brator-section-header-title">
                            <h2>You May Also Like</h2>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="brator-product-slider splide js-splide p-splide" data-splide='{"pagination":false,"type":"loop","perPage":5,"perMove":"1","gap":30, "breakpoints":{ "520" :{ "perPage": "1" },"746" :{ "perPage": "2" }, "768" :{ "perPage" : "3" }, "1090":{ "perPage" : "3" }, "1366":{ "perPage" : "4" }, "1500":{ "perPage" : "4" }, "1920":{ "perPage" : "5" }}}'>
                        <div class="splide__arrows style-two">
                            <button class="splide__arrow splide__arrow--prev">
                                <svg class="bi bi-chevron-right" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"></path>
                                </svg>
                            </button>
                            <button class="splide__arrow splide__arrow--next">
                                <svg class="bi bi-chevron-right" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="splide__track">
                            <div class="splide__list">
                                @foreach ($similar as $related)
                                    @include('partials.product-card', ['product' => $related])
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- bread end-->
    <div class="brator-plan-pixel-area">
        <div class="container-xxxl container-xxl container">
            <div class="col-12">
                <div class="plan-pixel-area"></div>
            </div>
        </div>
    </div>
    <!-- bread start-->
    <div class="brator-deal-product-slider recently-view">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-12">
                    <div class="brator-section-header">
                        <div class="brator-section-header-title">
                            <h2>Recently Viewed</h2>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="brator-product-slider splide js-splide p-splide" data-splide='{"pagination":false,"type":"loop","perPage":5,"perMove":"1","gap":30, "breakpoints":{ "520" :{ "perPage": "1" },"746" :{ "perPage": "2" }, "767" :{ "perPage" : "2" }, "1090":{ "perPage" : "3" }, "1366":{ "perPage" : "4" }, "1500":{ "perPage" : "4" }, "1920":{ "perPage" : "5" }}}'>
                        <div class="splide__arrows style-two">
                            <button class="splide__arrow splide__arrow--prev">
                                <svg class="bi bi-chevron-right" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"></path>
                                </svg>
                            </button>
                            <button class="splide__arrow splide__arrow--next">
                                <svg class="bi bi-chevron-right" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="splide__track">
                            <div class="splide__list">
                                @forelse ($recentlyViewed as $recentProduct)
                                    @include('partials.product-card', ['product' => $recentProduct])
                                @empty
                                    <p>Nothing viewed yet — the parts you look at will appear here.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- bread end-->
@endsection
