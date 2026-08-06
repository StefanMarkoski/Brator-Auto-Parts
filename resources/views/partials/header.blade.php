@use('App\Domain\Ordering\Support\DeliveryCharge')
    <!-- Header one start-->
    <div class="brator-header-top-bar-area design-one dark-bg">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-6 col-12">
                    {{--
                        THE PROMO BAR, driven by coupons.

                        The theme shipped "BLACK FRIDAY: Discount up to 50% use code Brator50"
                        here — a code that never existed, promised on every page. It was replaced
                        with the free-delivery threshold, which at least was true.

                        Now it lists every live coupon, one line each, and falls back to the
                        delivery threshold when there are none. The fallback matters: an empty
                        promo bar is a hole in the design, and a hardcoded offer is how the theme
                        got it wrong in the first place. Every branch states something the shop
                        will actually honour.

                        Each line is its own copy of the theme's OWN top-bar block rather than
                        extra <p> tags inside one. That block is a plain div, so repeating it
                        stacks the offers vertically for free; the theme styles its container
                        `display: flex; flex-wrap: wrap`, so putting several <p> in one would lay
                        them out side by side instead of below one another. No new CSS either way.
                    --}}
                    @php($offers = $advertisedCoupons)
                    @foreach ($offers as $offer)
                        @php($parts = $offer->promotionParts())
                        <div class="brator-header-top-bar-info-left">
                            {{-- "Start shopping" only on the last line: it is one call to action
                                 for the bar, not one per offer. --}}
                            <p><span class="c-ts">{{ $parts['headline'] }}</span><span>{{ $parts['condition'] }}</span><span class="c-ts">{{ $parts['code'] }}</span></p>@if ($loop->last)<a href="{{ route('shop.categories', [], false) }}">Start shopping</a>@endif
                        </div>
                    @endforeach

                    @if ($offers->isEmpty())
                        <div class="brator-header-top-bar-info-left">
                            <p><span class="c-ts">FREE DELIVERY</span><span>on orders over</span><span class="c-ts">{{ DeliveryCharge::freeFrom()->format() }}</span></p><a href="{{ route('shop.categories', [], false) }}">Start shopping</a>
                        </div>
                    @endif
                </div>
                <div class="col-lg-6 col-12">
                    <div class="brator-header-top-bar-info-right">
                        {{-- Was "Sell on Brator", a dead link to a marketplace signup. This is
                             a single-seller shop, so it links to the people who run it. --}}
                        <div class="brator-header-top-bar-info-right-link"><a href="{{ route('contact', [], false) }}">Contact us</a></div>
                        <div class="brator-header-top-bar-info-right-content">
                            {{--
                                Was a switcher offering US Dollar / US URO / US BD. This shop is
                                single-currency MKD and nothing converts anything, so the control
                                implied a feature that does not exist — and two of its three
                                options were not currencies. The fact is kept, the control is not.
                            --}}
                            <p>Prices in:</p><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/lang.png" alt="alt" />
                            <span>{{ config('shop.currency_symbol') }} ({{ config('shop.currency') }})</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="brator-header-area header-three header-one dark-bg">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-12 col-xl-4 col-xxl-3">
                    <div class="brator-logo-area">
                        <div class="brator-infobox__btn" id="infobox__toggle-js">
                            <svg x="0px" y="0px" width="451.847px" height="451.847px" viewBox="0 0 451.847 451.847" style="enable-background:new 0 0 451.847 451.847;" xml:space="preserve">
                                <g>
                                    <path fill="#fff" d="M225.923,354.706c-8.098,0-16.195-3.092-22.369-9.263L9.27,151.157c-12.359-12.359-12.359-32.397,0-44.751
                                        c12.354-12.354,32.388-12.354,44.748,0l171.905,171.915l171.906-171.909c12.359-12.354,32.391-12.354,44.744,0
                                        c12.365,12.354,12.365,32.392,0,44.751L248.292,345.449C242.115,351.621,234.018,354.706,225.923,354.706z"></path>
                                </g>
                                <g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g><g></g>
                            </svg>
                        </div>
                        <div class="brator-logo"><a href="{{ route('home', [], false) }}"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/logo-white.png" alt="logo" /></a>
                            <button>
                                <svg class="bi bi-pause" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M6 3.5a.5.5 0 0 1 .5.5v8a.5.5 0 0 1-1 0V4a.5.5 0 0 1 .5-.5zm4 0a.5.5 0 0 1 .5.5v8a.5.5 0 0 1-1 0V4a.5.5 0 0 1 .5-.5z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-5 col-xl-4 lg-dextop-none">
                    <div class="brator-search-area">
                        <form class="search-form" role="search" method="get" action="{{ route('search', [], false) }}">
                            <div class="select-search">
                                {{--
                                    Was three currency options in a search-SCOPE dropdown, and the
                                    select had no name attribute, so picking one submitted nothing.
                                    Now the real departments, posted as `in`, which SearchController
                                    resolves to a category path.
                                --}}
                                <select name="in">
                                    <option value="">All departments</option>
                                    @foreach ($navCategories as $department)
                                        <option value="{{ $department['slug'] }}" @selected(request('in') === $department['slug'])>{{ $department['name'] }}</option>
                                    @endforeach
                                </select>
                                <svg fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64" xml:space="preserve">
                                    <g>
                                        <path d="M32,46.8c-1.2,0-2.4-0.4-3.4-1.3L1.8,20.3c-0.7-0.7-0.7-1.8-0.1-2.5c0.7-0.7,1.8-0.7,2.5-0.1L31,42.9c0.5,0.5,1.4,0.5,2,0                                l26.8-25.2c0.7-0.7,1.8-0.6,2.5,0.1c0.7,0.7,0.6,1.8-0.1,2.5L35.4,45.4C34.4,46.3,33.2,46.8,32,46.8z"></path>
                                    </g>
                                </svg>
                            </div>
                            <input class="search-field" type="search" placeholder="Search by Part Name ..." name="s" required="required" />
                            <button type="submit">
                                <svg fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                    <path d="M62.1,57L44.6,42.8c3.2-4.2,5-9.3,5-14.7c0-6.5-2.5-12.5-7.1-17.1v0c-9.4-9.4-24.7-9.4-34.2,0C3.8,15.5,1.3,21.6,1.3,28                                c0,6.5,2.5,12.5,7.1,17.1c4.7,4.7,10.9,7.1,17.1,7.1c6.1,0,12.1-2.3,16.8-6.8l17.7,14.3c0.3,0.3,0.7,0.4,1.1,0.4                                c0.5,0,1-0.2,1.4-0.6C63,58.7,62.9,57.6,62.1,57z M10.8,42.7C6.9,38.8,4.8,33.6,4.8,28s2.1-10.7,6.1-14.6c4-4,9.3-6,14.6-6                                c5.3,0,10.6,2,14.6,6c3.9,3.9,6.1,9.1,6.1,14.6S43.9,38.8,40,42.7C32,50.7,18.9,50.7,10.8,42.7z"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="brator-info-right">
                        <div class="header-support-info">
                            <div class="header-support-info-icon">
                                <svg fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64" xml:space="preserve">
                                    <g>
                                        <path d="M49.1,61.3c-8.2,0-20-5.9-30.8-16.2C3.6,31.1-2.7,15.5,3.7,8.7C4,8.4,4.3,8.2,4.7,8l8.4-4.7c1.9-1,4.3-0.5,5.5,1.2l6.1,8.7                                    c0.6,0.9,0.9,2,0.7,3c-0.2,1.1-0.8,2-1.7,2.6L20,21.2c-0.2,0.1-0.2,0.2-0.2,0.3c0,0.1,0,0.2,0.1,0.3c2.7,4,10.4,14.2,22.6,21.5                                    c0.3,0.2,0.8,0.1,1-0.1l2.6-3.5c1.3-1.8,3.8-2.2,5.7-1l9.1,5.8c1.9,1.2,2.5,3.6,1.3,5.5l-5,8c0,0,0,0,0,0c-0.2,0.4-0.5,0.7-0.8,0.9                                    C54.5,60.6,52,61.3,49.1,61.3z M15.2,6.2c-0.1,0-0.2,0-0.4,0.1L6.4,11c-0.1,0.1-0.1,0.1-0.2,0.1C2,15.6,6.8,29.3,20.8,42.6                                    c14,13.3,28.5,17.9,33.3,13.8c0,0,0,0,0.1-0.1l5-8c0.1-0.2,0.1-0.5-0.2-0.7l-9.1-5.8c-0.3-0.2-0.8-0.1-1,0.1l-2.6,3.5                                    c-1.3,1.7-3.7,2.2-5.6,1.1C27.8,38.8,19.8,28.1,17,23.8c-0.6-0.9-0.8-1.9-0.6-3c0.2-1,0.8-2,1.7-2.5l3.7-2.5                                    c0.2-0.1,0.2-0.2,0.2-0.3c0-0.1,0-0.2-0.1-0.4l-6.1-8.7C15.7,6.3,15.4,6.2,15.2,6.2z M55.7,57.1L55.7,57.1L55.7,57.1z"></path>
                                    </g>
                                </svg>
                            </div>
                            <div class="header-support-info-l">
                                {{-- Was the theme's invented "1800 500 1234", presented as this shop's
                                     support line. Points at the contact page until there is a real
                                     number to publish. --}}
                                <h6>Support:</h6><a href="{{ route('contact', [], false) }}">Contact us</a>
                            </div>
                        </div>
                        {{--
                            The wishlist heart is REMOVED. There is no wishlist: it was a dead link
                            displaying a hardcoded "0", so it always said you had saved nothing and
                            could never let you save anything.
                        --}}
                        <div class="brator-cart-link"><a href="{{ route('cart', [], false) }}">
                                <div class="brator-cart-icon click-item-count">
                                    <svg fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64">
                                        <g>
                                            <path d="M40.9,48.2c-3.9,0-7.1,3.3-7.1,7.3c0,4,3.2,7.3,7.1,7.3s7.1-3.3,7.1-7.3C48.1,51.5,44.9,48.2,40.9,48.2z M40.9,59.3                                        c-2,0-3.6-1.7-3.6-3.8c0-2.1,1.6-3.8,3.6-3.8s3.6,1.7,3.6,3.8C44.6,57.6,42.9,59.3,40.9,59.3z"></path>
                                            <path d="M18.2,48.2c-3.9,0-7.1,3.3-7.1,7.3c0,4,3.2,7.3,7.1,7.3s7.1-3.3,7.1-7.3C25.4,51.5,22.2,48.2,18.2,48.2z M18.2,59.3                                        c-2,0-3.6-1.7-3.6-3.8c0-2.1,1.6-3.8,3.6-3.8s3.6,1.7,3.6,3.8C21.9,57.6,20.2,59.3,18.2,59.3z"></path>
                                            <path d="M57.8,1.3h-6.4c-1.5,0-2.8,1.1-3,2.6l-1.8,13.2H7.3c-0.9,0-1.7,0.4-2.2,1.1c-0.5,0.7-0.7,1.6-0.5,2.4c0,0,0,0.1,0,0.1                                        l6.1,18.9c0.3,1.2,1.4,2.1,2.8,2.1h29.5c2.2,0,4-1.6,4.3-3.8l4.6-33.2h6c1,0,1.8-0.8,1.8-1.8S58.8,1.3,57.8,1.3z M43.7,37.4                                        c-0.1,0.4-0.4,0.8-0.9,0.8h-29L8.1,20.6h37.9L43.7,37.4z"></path>
                                        </g>
                                    </svg><span>{{ $basketCount ?? 0 }}</span>
                                </div>
                            </a>
                            <div class="brator-cart-item-list">
                                <div class="brator-cart-item-list-header">
                                    <h2>Cart<span> (5 items)</span></h2>
                                    <button class="brator-cart-close">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
                                        </svg>
                                    </button>
                                </div>
                                @include('partials.mini-cart-items')
                                <div class="brator-cart-item-list-money-area">
                                    <div class="brator-cart-item-money"><span>Subtotal (excl. VAT)</span><span>{{ $miniCart->subtotal->format() }}</span></div>
                                    <div class="brator-cart-item-money"><span>VAT ({{ (int) config('shop.vat_rate') }}%)</span><span>{{ $miniCart->vat->format() }}</span></div>
                                </div>
                                <div class="brator-cart-total-money">
                                    <div class="brator-cart-total-header"><span>total</span><span>{{ $miniCart->total->format() }}</span></div>
                                    <div class="brator-cart-total-action"><a href="{{ route('cart', [], false) }}">View Cart</a><a href="{{ route('cart', [], false) }}">Checkout</a></div>
                                </div>
                            </div>
                        </div>
                        {{--
                            The "Sign In" link is REMOVED. There are no customer accounts — guests
                            check out and the only login is the staff one at /admin — so this
                            promised an account a shopper could never create.
                        --}}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="brator-header-menu-area dark-bg cat-header">
        <div class="close-menu-bg"></div>
        <div class="brator-mega-menu-close">
            <svg class="bi bi-x" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
            </svg>
        </div>
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-12 col-xl-4 col-xxl-3">
                    <div class="menu-cat-list-area"><span class="icon">
                            <svg fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64" xml:space="preserve">
                                <g>
                                    <path d="M61,30.3H3c-1,0-1.8,0.8-1.8,1.8S2,33.8,3,33.8h58c1,0,1.8-0.8,1.8-1.8S62,30.3,61,30.3z"></path>
                                    <path d="M61,47.9H3c-1,0-1.8,0.8-1.8,1.8S2,51.4,3,51.4h58c1,0,1.8-0.8,1.8-1.8S62,47.9,61,47.9z"></path>
                                    <path d="M3,16.1h58c1,0,1.8-0.8,1.8-1.8S62,12.6,61,12.6H3c-1,0-1.8,0.8-1.8,1.8S2,16.1,3,16.1z"></path>
                                </g>
                            </svg></span><span class="text">All Categories</span>
                        <div class="dropdown-icon-cat">
                            <svg fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64" xml:space="preserve">
                                <g>
                                    <path d="M32,46.8c-1.2,0-2.4-0.4-3.4-1.3L1.8,20.3c-0.7-0.7-0.7-1.8-0.1-2.5c0.7-0.7,1.8-0.7,2.5-0.1L31,42.9c0.5,0.5,1.4,0.5,2,0                            l26.8-25.2c0.7-0.7,1.8-0.6,2.5,0.1c0.7,0.7,0.6,1.8-0.1,2.5L35.4,45.4C34.4,46.3,33.2,46.8,32,46.8z"></path>
                                </g>
                            </svg>
                        </div>
                        <div class="menu-cat-list-item">
                            {{--
                                The "All Categories" dropdown was TWENTY hardcoded department
                                names from the theme's demo data — Air Filters, Clearance,
                                "Entertaiments" (their typo), Tires Chains, "sanex" — none of
                                which exist in this shop, every one a dead link to #_. A shopper
                                opening the main category menu was reading another site's catalogue.

                                Now the real tree, from the same navigation query the mega menu
                                uses, with the theme's own nesting markup preserved.
                            --}}
                            <ul>
                                @foreach ($navCategories as $department)
                                    <li>
                                        <a href="{{ route('shop.category', $department['slug'], false) }}">{{ $department['name'] }}</a>

                                        @if ($department['children'] !== [])
                                            <svg fill="#000000" width="14px" height="14px" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64" style="enable-background:new 0 0 64 64;" xml:space="preserve">
                                        <g>
                                            <path d="M19,62.8c-0.4,0-0.9-0.2-1.2-0.5c-0.7-0.7-0.7-1.8-0.1-2.5L42.9,33c0.5-0.5,0.5-1.4,0-2L17.7,4.2c-0.7-0.7-0.6-1.8,0.1-2.5
                                                c0.7-0.7,1.8-0.6,2.5,0.1l25.2,26.8c1.7,1.9,1.7,4.9,0,6.7L20.3,62.2C19.9,62.6,19.5,62.8,19,62.8z"/>
                                        </g>
                                    </svg>
                                            <ul>
                                                @foreach ($department['children'] as $child)
                                                    <li><a href="{{ route('shop.category', $child['slug'], false) }}">{{ $child['name'] }}</a></li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-5 col-xl-4 xxl-dextop-none">
                    <div class="brator-header-menu-with-info">
                        <div class="brator-header-menu">
                            <ul class="list-style-outside-none">
                                <li><a href="{{ route('home', [], false) }}">Home</a></li>
                                <li class="mega-menu-li"><a href="{{ route('shop.categories', [], false) }}">Auto Parts <span class="count-hot-beg">MEGA</span></a>
                                    <div class="mega-menu-area">
                                        <div class="mega-menu-cat-list">
                                            @foreach (collect($navCategories ?? [])->split(3) as $column)
                                                <div class="mega-menu-cat-list-left mega-menu-cat-list-single-area">
                                                    @foreach ($column as $navCategory)
                                                        <a href="{{ route('shop.category', $navCategory['slug'], false) }}"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $navCategory['image'] }}" alt="{{ $navCategory['name'] }}" /><span>{{ $navCategory['name'] }}</span></a>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                            <div class="brator-offer-box-two lazyload" data-bg="/assets/images/offer/offer-02.png">
                                                <div class="budget-area"><span>mega bundle</span></div>
                                                <h2>Service Kits</h2>
                                                <p>Everything for a full service</p>
                                                <h6><span>Shop the range</span></h6><a href="{{ route('shop.categories', [], false) }}">Shop Now</a>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                @foreach (collect($navCategories ?? [])->take(4) as $navCategory)
                                    @if (count($navCategory['children']))
                                        <li class="down-menu"><a href="{{ route('shop.category', $navCategory['slug'], false) }}">{{ $navCategory['name'] }}</a>
                                            <ul>
                                                @foreach ($navCategory['children'] as $navChild)
                                                    <li><a href="{{ route('shop.category', $navChild['slug'], false) }}">{{ $navChild['name'] }}</a></li>
                                                @endforeach
                                            </ul>
                                        </li>
                                    @endif
                                @endforeach
                                <li><a href="{{ route('about', [], false) }}">About us</a></li>
                                <li><a href="{{ route('contact', [], false) }}">Contact Us</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 xxl-dextop-none">
                    {{-- "Track Order" and "Free Return" removed: neither exists. A receipt is
                                 emailed, and returns are handled by talking to the shop. --}}
                            <div class="cat-menu-info-s"><a class="cat-menu-info-s-item" href="#_">
                            <svg id="lni_lni-reload" fill="#000000" width="52" height="52" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64" xml:space="preserve">
                                <g>
                                    <path d="M7.4,28.5c0.3,0,0.6,0,0.8-0.1l11.1-3.9c0.9-0.3,1.4-1.3,1.1-2.2c-0.3-0.9-1.3-1.4-2.2-1.1l-6.7,2.4                                c3.3-9.1,12-15.3,22.1-15.3c10.7,0,20.1,7.1,22.7,17.4c0.2,0.9,1.2,1.5,2.1,1.3c0.9-0.2,1.5-1.2,1.3-2.1c-3-11.8-13.8-20-26.1-20                                c-12,0-22.4,7.7-25.8,18.9l-3.1-8.7c-0.3-0.9-1.3-1.4-2.2-1.1c-0.9,0.3-1.4,1.3-1.1,2.2l3.8,10.9C5.5,27.9,6.5,28.5,7.4,28.5z"></path>
                                    <path d="M62.6,49.9l-4.1-10.8c-0.2-0.6-0.7-1.1-1.3-1.3c-0.6-0.2-1.2-0.3-1.8,0l-11,4.2c-0.9,0.3-1.4,1.4-1,2.3                                c0.3,0.9,1.4,1.4,2.3,1l6.8-2.6c-3.8,7.9-11.9,13.1-21.1,13.1c-10.1,0-19-6.3-22.1-15.7C8.9,39.2,7.9,38.7,7,39                                c-0.9,0.3-1.4,1.3-1.1,2.2C9.5,52,19.7,59.3,31.3,59.3c11,0,20.8-6.5,24.8-16.4l3.2,8.3c0.3,0.7,0.9,1.1,1.6,1.1                                c0.2,0,0.4,0,0.6-0.1C62.5,51.8,63,50.8,62.6,49.9z"></path>
                                </g>
                            </svg><span>Recently Viewed</span></a></div>
                </div>
            </div>
        </div>
    </div>
    <div class="brator-slide-menu-area dark-bg">
        <div class="brator-slide-menu-bg"></div>
        <div class="brator-slide-menu-content">
            <div class="brator-slide-menu-close">
                <svg class="bi bi-x" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
                </svg>
            </div>
            <div class="brator-slide-logo-items"></div>
            <div class="brator-slide-menu-items"></div>
        </div>
    </div>
    <div class="brator-header-menu-area scroll-menu">
        <div class="close-menu-bg"></div>
        <div class="brator-mega-menu-close">
            <svg class="bi bi-x" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
            </svg>
        </div>
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="brator-header-menu-with-info">
                        <div class="brator-header-menu">
                            <ul class="list-style-outside-none">
                                <li><a href="{{ route('home', [], false) }}">Home</a></li>
                                <li class="mega-menu-li"><a href="{{ route('shop.categories', [], false) }}">Auto Parts <span class="count-hot-beg">MEGA</span></a>
                                    <div class="mega-menu-area">
                                        <div class="mega-menu-cat-list">
                                            @foreach (collect($navCategories ?? [])->split(3) as $column)
                                                <div class="mega-menu-cat-list-left mega-menu-cat-list-single-area">
                                                    @foreach ($column as $navCategory)
                                                        <a href="{{ route('shop.category', $navCategory['slug'], false) }}"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $navCategory['image'] }}" alt="{{ $navCategory['name'] }}" /><span>{{ $navCategory['name'] }}</span></a>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                            <div class="brator-offer-box-two lazyload" data-bg="/assets/images/offer/offer-02.png">
                                                <div class="budget-area"><span>mega bundle</span></div>
                                                <h2>Service Kits</h2>
                                                <p>Everything for a full service</p>
                                                <h6><span>Shop the range</span></h6><a href="{{ route('shop.categories', [], false) }}">Shop Now</a>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                @foreach (collect($navCategories ?? [])->take(4) as $navCategory)
                                    @if (count($navCategory['children']))
                                        <li class="down-menu"><a href="{{ route('shop.category', $navCategory['slug'], false) }}">{{ $navCategory['name'] }}</a>
                                            <ul>
                                                @foreach ($navCategory['children'] as $navChild)
                                                    <li><a href="{{ route('shop.category', $navChild['slug'], false) }}">{{ $navChild['name'] }}</a></li>
                                                @endforeach
                                            </ul>
                                        </li>
                                    @endif
                                @endforeach
                                <li><a href="{{ route('about', [], false) }}">About us</a></li>
                                <li><a href="{{ route('contact', [], false) }}">Contact Us</a></li>
                            </ul>
                        </div>
                        {{-- Was "Order Status", a dead link. There is no order tracking. --}}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Header one end-->
