@use('App\Domain\Ordering\Support\DeliveryCharge')
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
                                        {{--
                                            Two bugs in four lines. The paths were RELATIVE —
                                            url(./assets/images/…) — so on /product/{slug} the
                                            browser asked for /product/assets/… and got four 404s
                                            in the console on every product page. And they were the
                                            theme's placeholder files rather than this product's
                                            photographs, so the thumbnail strip never matched the
                                            large image it switches between.

                                            Root-relative now, and driven by the same image list as
                                            the panes below.
                                        --}}
                                        @foreach (range(0, 3) as $slot)
                                            @php($thumb = $product->images[$slot]->path ?? 'assets/images/product-tab-img-0'.($slot + 1).'.jpeg')
                                            <li><a class="js-tabs__title" href="#" style="background-image:url(/{{ $thumb }})"></a></li>
                                        @endforeach
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
                                    {{-- Was hardcoded "Sparegold", the theme's demo brand, on every product. Links to the
                                         brand's own filtered listing when there is a brand. --}}
                                    <div class="brator-product-hero-content-brand">
                                        @if ($product->brand)
                                            <a href="{{ route('search', ['brand' => [$product->brand->slug]], false) }}">{{ $product->brand->name }}</a>
                                        @else
                                            <a href="{{ route('shop.categories', [], false) }}">Unbranded</a>
                                        @endif
                                    </div>
                                    {{--
                                        The brand mark, or the brand's NAME when we have no logo.

                                        The seeder used to fill logo_path with one of the theme's
                                        own brand images — other companies' real logos, 18 shared
                                        across 36 brands — so a Gates caliper displayed an "otyres"
                                        mark. Showing a third party's branding on somebody else's
                                        product is worse than showing nothing.
                                    --}}
                                    <div class="brator-product-hero-content-brand-img">
                                        @if ($product->brand?->logo_path)
                                            <a href="{{ route('search', ['brand' => [$product->brand->slug]], false) }}"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="/{{ $product->brand->logo_path }}" alt="{{ $product->brand->name }}" /></a>
                                        @elseif ($product->brand)
                                            <a href="{{ route('search', ['brand' => [$product->brand->slug]], false) }}">{{ $product->brand->name }}</a>
                                        @endif
                                    </div>
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
                                            {{--
                                                The star is shown only when the answer is POSITIVE.

                                                The theme has one class for this box and it is
                                                green, so a "does not fit" message was appearing
                                                with an approving star in a green panel — the
                                                styling contradicting the words. Dropping the star
                                                is as far as this can be fixed without adding CSS,
                                                which needs Stefan's say-so. The colour is still
                                                wrong for the negative case and is flagged.
                                            --}}
                                            @if ($fitsChosenVehicle !== false)
                                                <svg class="active" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                            @endif
                                            <span>@if ($fitsChosenVehicle === true){{ $chosenVehicleName ? 'Fits your '.$chosenVehicleName : 'Fits your vehicle' }}@elseif ($fitsChosenVehicle === false){{ $chosenVehicleName ? 'Not listed as fitting your '.$chosenVehicleName.' — check before ordering' : 'Not listed as fitting your vehicle — check before ordering' }}@else{{ $product->stock_status->isBuyable() ? 'Ready to ship' : 'Currently unavailable' }}@endif</span>
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
                                                    {{-- data-qty-step is what storefront.js binds. The
                                                     CART's +/- look identical but are real submits
                                                     carrying name="step", so the attribute keeps the
                                                     two apart by construction rather than by
                                                     inspecting type="button". --}}
                                                <button class="decrement-count-qty" type="button" data-qty-step="-1" aria-label="Decrease quantity">-</button>
                                                    <input type="number" name="quantity" value="1" min="1" max="99" />
                                                    <button class="add-count-qty" type="button" data-qty-step="1" aria-label="Increase quantity">+</button>
                                                </div>
                                            </div>
                                            <div class="brator-product-single-cart-add">
                                                <button type="submit" @disabled(! $product->stock_status->isBuyable())>Add To Cart</button>
                                            </div>
                                        </div>
                                    </form>
                                    <div class="brator-product-single-cart-action">
                                        {{--
                                        "Add to Wishlist" and "Add to Compare" are REMOVED. Neither
                                        feature exists — both were explicitly out of scope — and both
                                        were <button> elements with no handler, so they depressed and
                                        did nothing. Share is kept: it works.
                                    --}}
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
                                        {{-- Was "Ship to North Hills, CA 91343" — a Los Angeles suburb, hardcoded by
                                     the theme's authors, on a shop that delivers in North Macedonia.
                                     Now the real delivery promise, from the rule that sets it. --}}
                                <p><span>Delivery</span> free over {{ DeliveryCharge::freeFrom()->format() }}, otherwise {{ DeliveryCharge::flatRate()->format() }}</p>
                                    </div>
                                    <div class="brator-product-single-light-info">
                                        <div class="brator-product-single-light-info-s cat">
                                            <h5>Categories: </h5>@foreach ($product->categories as $category)<a href="{{ route('shop.category', $category->slug, false) }}">{{ $category->name }}</a>@endforeach
                                        </div>
                                        <div class="brator-product-single-light-info-s">
                                            <h5>Part Number: </h5>@foreach ($product->crossReferences->take(3) as $ref)<a href="{{ route('search', ['s' => $ref->number], false) }}">{{ $ref->number }}</a>@endforeach
                                        </div>
                                        <div class="brator-product-single-light-info-s">
                                            {{-- Was "wheels tires rims sliver mercedes glc" on every
                                                 part, dead links to #_. Now the product's own
                                                 categories and brand, each linking somewhere real. --}}
                                            <h5>Tags:</h5>@foreach ($product->categories->take(3) as $tag)<a href="{{ route('shop.category', $tag->slug, false) }}">{{ Str::lower($tag->name) }}</a>@endforeach @if ($product->brand)<a href="{{ route('search', ['brand' => [$product->brand->slug]], false) }}">{{ Str::lower($product->brand->name) }}</a>@endif
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
    {{--
        HIDDEN WHEN NOTHING HAS EVER BEEN BOUGHT WITH THIS PART.

        The companions come from receipt lines now, not from the seeded recommendations table, so
        for most parts there genuinely are none — 590 of 5,000 have a companion today. Showing the
        strip anyway would leave "Frequently Bought Together" above the product on its own, which
        both looks broken and claims a pairing that has never happened.
    --}}
    @if (count($bundle) > 1)
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
                            {{--
                                align-items: stretch, overriding the theme's "center".

                                The cards are boxes with visible borders, and the theme centres them
                                vertically — so a card that is taller than its neighbours sits proud of
                                the row at both ends. That happens to any discounted part: the sale
                                price and the struck-through original do not fit on one line at 235px,
                                making its price block 56px instead of 28px and lifting the whole card
                                14px above the others.

                                Stretch is the flex default, and it lines the boxes up top and bottom.
                                Inline rather than in a stylesheet, so the purchased CSS stays
                                byte-identical, and it introduces no new class.
                            --}}
                            <div class="product-list-items check-box-product" style="align-items: stretch">
                                {{--
                                    One card per item, the current part first, each carrying its own
                                    checkbox — which is exactly the theme's structure for this block.
                                    The "+" between cards is drawn by the checkbox's own CSS, so it
                                    only appears when the checkbox sits inside the card.
                                --}}
                                @foreach ($bundle as $index => $item)
                                    @include('partials.product-card', [
                                        'product' => $item,
                                        'variant' => 'design-two',
                                        'bundleCheckbox' => [
                                            'name' => 'product_ids[]',
                                            'value' => $item->id,
                                            'price' => $item->price->toPrimitive(),
                                            // The theme shows no text beside the box, so this is where
                                            // "this is the part you are looking at" has to live.
                                            'label' => ($index === 0 ? 'This item: ' : 'Also add: ').$item->name,
                                            'disabled' => ! $item->inStock,
                                        ],
                                    ])
                                @endforeach
                            </div>
                            <div class="brator-product-single-frequently-total">
                                <h6>Total:</h6><span data-bundle-total>{{ $bundleTotal->format() }}</span>
                                <button type="submit">Add All To Cart</button>
                            </div>
                        </form>
                    </div>
                </div>
                {{--
                    The theme's "Guide & Blog" sidebar is REMOVED, not left rendering.

                    It advertised four articles — "Replace Brakes Guide", "Things to keep in
                    mind when washing a car" — each a dead link to #_, eight placeholder links
                    on every product page. Blog pages are out of scope by Stefan's decision, so
                    this was promising content that will never exist and cannot be clicked.

                    Deleting state-displaying markup is within the standing rule: nothing is
                    restyled and no CSS class is introduced. The product column beside it keeps
                    its own grid classes, so the layout closes up as the theme intends.
                --}}
            </div>
        </div>
    </div>
    @endif
    <div class="brator-product-single-tab-area design-one-m">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-xxl-8 col-md-12">
                    <div class="brator-product-single-tab-list js-tabs" id="tabs-product-content">
                        <div class="brator-product-single-tab-header js-tabs__header">
                            <ul>
                                <li><a class="js-tabs__title" href="#">Description </a></li>
                                <li><a class="js-tabs__title" href="#">Specification </a></li>
                                <li><a class="js-tabs__title" href="#">Reviews ({{ number_format($product->reviews_count) }}) </a></li>
                                <li><a class="js-tabs__title" href="#">Product Q&A</a></li>
                            </ul>
                        </div>
                        <div class="js-tabs__content brator-product-single-tab-item">
                            {{--
                                The theme's demo text here described ALLOY WHEELS — hub centering
                                rings, TPMS sensor compatibility, a five-year structural warranty
                                from "TSW", a finish warranty from "SG" — and it rendered on every
                                product, so a brake fluid page promised a warranty on its chrome
                                finish. Written by the theme's authors about their own sample data.

                                Replaced with the product's real copy, and a description is not
                                invented when there is none.
                            --}}
                            @if ($product->description || $product->short_description)
                                {!! $product->description ?: e($product->short_description) !!}
                            @else
                                <p>No description has been written for this part yet. The
                                    specification tab lists what we know about it.</p>
                            @endif

                            @php($featured = $product->attributeValues->filter(fn ($v) => $v->value_string)->take(8))

                            @if ($featured->isNotEmpty())
                                <h6>featured</h6>
                                <ul>
                                    @foreach ($featured as $value)
                                        <li>{{ $value->attribute->name }}: {{ $value->value_string }}</li>
                                    @endforeach
                                </ul>
                            @endif
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
                                    {{-- Was a hardcoded "4.5/5", four-and-a-bit stars and "14 Reviews"
                                         on every product regardless of its actual rating. --}}
                                    <div class="brator-review-pint-count">
                                        <h6>{{ $product->reviews_count > 0 ? number_format($product->rating_avg, 1).'/5' : 'Not rated' }} </h6>
                                    </div>
                                    <div class="brator-review-comment-count">
                                        <div class="brator-review">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <svg class="{{ $i <= round($product->rating_avg) ? 'active' : 'd-active' }}" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                    <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                </svg>
                                            @endfor
                                        </div>
                                        <div class="brator-review-text">
                                            <p>{{ number_format($product->reviews_count) }} {{ Str::plural('Review', $product->reviews_count) }}</p>
                                        </div>
                                    </div>
                                <div class="brator-review-comment-list">
                                    {{--
                                        Was three identical hardcoded reviews, all by "Paulo Dybala"
                                        on 25 April 2022, praising a holiday cottage. Now the real
                                        approved reviews, already eager-loaded on the detail query.

                                        The theme ships three profile photographs and we have no
                                        avatars, so they cycle — decorative, and the alternative is a
                                        broken image.
                                    --}}
                                    @forelse ($product->reviews as $review)
                                        <div class="brator-review-comment-single-item">
                                            <div class="brator-review-comment-single-img"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/profile-0{{ ($loop->index % 3) + 1 }}.jpg" alt="{{ $review->author_name }}" /></div>
                                            <div class="brator-review-comment-single-content">
                                                <div class="brator-review">
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        <svg class="{{ $i <= $review->rating ? 'active' : 'd-active' }}" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                                            <path d="M59.7,23.9l-18.1-2.8L33.4,3.9c-0.6-1.2-2.2-1.2-2.8,0l-8.2,17.3L4.4,23.9c-1.3,0.2-1.8,1.9-0.8,2.8l13.1,13.5l-3.1,18.9  c-0.2,1.3,1.1,2.4,2.3,1.6l16.3-8.9l16.2,8.9c1.1,0.6,2.5-0.4,2.2-1.6l-3.1-18.9l13.1-13.5C61.4,25.8,61,24.1,59.7,23.9z"></path>
                                                        </svg>
                                                    @endfor
                                                </div>
                                                <div class="brator-review-comment-single-title">
                                                    <h6>{{ $review->title }}</h6>
                                                    <p>{{ $review->body }}</p>
                                                </div>
                                                <div class="brator-review-comment-date">
                                                    <h6>{{ $review->author_name }} on {{ $review->created_at?->format('j F, Y') }}</h6>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="brator-review-comment-single-item">
                                            <div class="brator-review-comment-single-content">
                                                <div class="brator-review-comment-single-title">
                                                    <p>No reviews for this part yet.</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforelse
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
    {{--
        HIDDEN WHEN THERE IS NOTHING TO RECOMMEND.

        product_recommendations only covers 300 of the 5,000 products, so on most pages
        this was a "You May Also Like" heading over empty space — which reads as a strip
        that failed to load rather than as a product with no companions.
    --}}
    @if (count($similar ?? []))
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
                    {{-- The carousel classes are CONDITIONAL. The theme mounts Splide on every element with
                         the class "splide", and Splide on an EMPTY list still initialises: with
                         type:loop it computes a clone offset from nothing and translates the list
                         a few hundred pixels sideways, so the "nothing here yet" line appeared
                         shoved off to one side. No items, no carousel — just the message. --}}
                    <div class="brator-product-slider @if (count($similar ?? [])) splide js-splide p-splide @endif" data-splide='{"pagination":false,"type":"loop","perPage":5,"perMove":"1","gap":30, "breakpoints":{ "520" :{ "perPage": "1" },"746" :{ "perPage": "2" }, "768" :{ "perPage" : "3" }, "1090":{ "perPage" : "3" }, "1366":{ "perPage" : "4" }, "1500":{ "perPage" : "4" }, "1920":{ "perPage" : "5" }}}'>
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
    @endif
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
                    {{-- The carousel classes are CONDITIONAL. The theme mounts Splide on every element with
                         the class "splide", and Splide on an EMPTY list still initialises: with
                         type:loop it computes a clone offset from nothing and translates the list
                         a few hundred pixels sideways, so the "nothing here yet" line appeared
                         shoved off to one side. No items, no carousel — just the message. --}}
                    <div class="brator-product-slider @if (count($recentlyViewed ?? [])) splide js-splide p-splide @endif" data-splide='{"pagination":false,"type":"loop","perPage":5,"perMove":"1","gap":30, "breakpoints":{ "520" :{ "perPage": "1" },"746" :{ "perPage": "2" }, "767" :{ "perPage" : "2" }, "1090":{ "perPage" : "3" }, "1366":{ "perPage" : "4" }, "1500":{ "perPage" : "4" }, "1920":{ "perPage" : "5" }}}'>
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
