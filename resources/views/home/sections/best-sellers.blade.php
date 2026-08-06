    <!-- Brator best seller product start -->
    <div class="brator-deal-product-slider brator-best-seller">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-12">
                    <div class="brator-best-seller-section-header-area">
                        <div class="brator-section-header all-item-left">
                            <div class="brator-section-header-title">
                                <h2>{{ $section->heading ?? 'Best Seller' }}</h2>
                            </div>
                            <a href="{{ route('shop.categories', [], false) }}">See All Products
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"></path>
                                </svg>
                            </a>
                        </div>
                        <div class="brator-best-seller-sub-filter-area">
                            {{--
                            Was five hardcoded tabs — "Top 10 / Top Auto Parts / Top Car Care /
                            Top Wheels & Tires / Top Tolls & Supplies" (their typo) — every one a
                            dead link, and three naming departments this shop does not have. They
                            now link to the real departments' listings, sorted by rating, which is
                            what a "top" tab is for.
                        --}}
                        <ul class="brator-best-seller-sub-filter-content">
                            <li class="brator-best-seller-sub-filter-list"><a class="active" href="{{ route('search', ['sort' => 'rating'], false) }}">Top 10</a></li>
                            @foreach (collect($navCategories)->take(4) as $department)
                                <li class="brator-best-seller-sub-filter-list"><a href="{{ route('shop.category', [$department['slug'], 'sort' => 'rating'], false) }}">Top {{ $department['name'] }}</a></li>
                            @endforeach
                        </ul>
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
                                @foreach ($section->items as $product)
                                    @include('partials.product-card')
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Brator best seller product end -->
