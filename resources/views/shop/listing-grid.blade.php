@extends('layouts.shop')

@section('title', 'Brator Auto Parts')

@section('content')
    <!-- Blog post grid start-->
    <div class="brator-banner-slider-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-md-12">
                    <div class="brator-banner-area design-four lazyload" data-bg="/assets/images/slider/slide-04.jpg">
                        <div class="brator-banner-content">
                            <h2><a href="{{ request()->fullUrl() }}">{{ $category?->name ?? ($searchTerm ? 'Search: '.$searchTerm : 'All parts') }}</a></h2>
                            <p>{{ $category?->description ?? 'Every part in the catalogue, filterable by vehicle, brand, price and specification.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Blog post grid end-->
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
    <!-- bread start-->
    <div class="brator-current-vehicle-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-12">
                    {{-- The vehicle is a FILTER, never a gate: with none chosen this
                         invites one, and clearing it restores the whole catalogue. --}}
                    <div class="brator-current-vehicle">
                        @if ($vehicle)
                            <div class="brator-current-vehicle-content">
                                <p>Your current vehicle</p>
                                <h4>Parts for<span> {{ $vehicle['label'] }} ({{ $vehicle['years'] }}) </span></h4>
                            </div>
                            <div class="brator-current-vehicle-content">
                                <form method="post" action="{{ route('vehicle.clear', [], false) }}">
                                    @csrf
                                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}" />
                                    <button type="submit">Show all vehicles</button>
                                </form>
                            </div>
                        @else
                            <div class="brator-current-vehicle-content">
                                <p>Showing parts for every vehicle</p>
                                <h4>Narrow it down<span> pick your car to see only parts that fit </span></h4>
                            </div>
                            <div class="brator-current-vehicle-content"><a href="{{ route('shop.categories', [], false) }}">Choose vehicle</a></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- bread end-->
    <!-- bread start-->
    <div class="brator-product-shop-page-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-3 md-display-none">
                    <div class="brator-sidebar-area design-one">
                        <div class="close-fillter">
                            <svg class="bi bi-x" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
                            </svg>
                        </div>
                        @include('partials.shop-departments')
                        <div class="brator-sidebar-single-item">
                            <div class="shop-sidebar-title fillter-list-title">
                                <h2>Filter</h2>
                                <button class="rest-all-checkbox">
                                    <svg class="bi bi-arrow-repeat" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"></path>
                                        <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"></path>
                                    </svg>Reset All
                                </button>
                            </div>
                            {{--
                                    The clear-filters form, declared OUTSIDE the filter form below.
                                    The button that submits it sits inside the sidebar and reaches
                                    it by id, because a form inside a form is invalid HTML and gets
                                    silently dropped.
                                --}}
                                <form method="post" id="clear-filters"
                                    action="{{ route('filters.clear', [], false) }}" hidden>
                                    @csrf
                                    <input type="hidden" name="redirect_to"
                                        value="{{ $category ? route('shop.category', $category->slug, false) : route('search', ['s' => $filter->searchTerm], false) }}" />
                                </form>
                                <form method="get" class="shop-sidebar-content" data-listing-filters>
                                @if ($searchTerm)<input type="hidden" name="s" value="{{ $searchTerm }}" />@endif
                                @if ($filter->listView)<input type="hidden" name="view" value="list" />@endif
                                @if ($filter->sort !== 'newest')<input type="hidden" name="sort" value="{{ $filter->sort }}" />@endif
                                @include('partials.filter-sidebar')
                                @include('partials.filter-brands')
                                @include('partials.filter-sidebar-lower')
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-9 col-12">
                    <div class="brator-best-product-slider">
                        <div class="brator-section-header">
                            <div class="brator-section-header-title">
                                <h2>Best Seller</h2>
                            </div>
                        </div>
                        <div class="brator-product-slider splide js-splide p-splide" data-splide='{"pagination":false,"type":"loop","perPage":4,"perMove":"1","gap":30, "breakpoints":{ "576" :{ "perPage": "1" },"746" :{ "perPage": "2" }, "768" :{ "perPage" : "2" }, "991":{ "perPage" : "3" }, "1399":{ "perPage" : "4" }, "1500":{ "perPage" : "4" }, "1920":{ "perPage" : "4" }}}'>
                            <div class="splide__arrows style-one">
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
                                    @forelse ($bestSellers as $sidebarProduct)
                                        @include('partials.product-card', ['product' => $sidebarProduct, 'variant' => 'design-two'])
                                    @empty
                                        <p>No sales recorded yet.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="brator-plan-pixel-area">
                        <div class="plan-pixel-area"></div>
                    </div>
                    <div class="brator-inline-product-filter-area" data-listing-summary>
                        <div class="brator-inline-product-filter-left">
                            <div class="brator-filter-show-result">
                                <p><span>{{ number_format($shownFrom) }} - {{ number_format($shownTo) }} </span>of {{ number_format($total) }} result{{ $total === 1 ? '' : 's' }}</p>
                            </div>
                            <div class="brator-filter-show-items">
                                <p>Show item</p>
                                <div class="brator-filter-show-items-count">
                                    @foreach (\App\Domain\Catalog\DTOs\ProductFilter::PER_PAGE_OPTIONS as $size)
                                        <a @class(['current' => $perPage === $size])
                                            href="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => null]) }}">{{ $size }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="brator-inline-product-filter-right">
                            <div class="brator-filter-short-by">
                                <p>Sort by</p>
                                <div class="brator-filter-show-items-count">
                                    <select data-auto-submit="navigate">
                                        @foreach ([
                                            'newest' => 'Newest first',
                                            'price_asc' => 'Price: low to high',
                                            'price_desc' => 'Price: high to low',
                                            'rating' => 'Best rated',
                                            'name' => 'Name A–Z',
                                        ] as $value => $label)
                                            <option value="{{ request()->fullUrlWithQuery(['sort' => $value, 'page' => null]) }}"
                                                @selected($filter->sort === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="brator-filter-view-type"><a @class(['current' => ! $listView]) href="{{ request()->fullUrlWithQuery(['view' => null, 'page' => null]) }}">
                                    <svg class="bi bi-grid" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z"></path>
                                    </svg></a><a @class(['current' => $listView]) href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => null]) }}">
                                    <svg class="bi bi-list-task" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path fill-rule="evenodd" d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5H2zM3 3H2v1h1V3z"></path>
                                        <path d="M5 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zM5.5 7a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 4a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9z"></path>
                                        <path fill-rule="evenodd" d="M1.5 7a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5V7zM2 7h1v1H2V7zm0 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5H2zm1 .5H2v1h1v-1z"></path>
                                    </svg></a>
                                <button class="filter-enable">fillter </button>
                            </div>
                        </div>
                    </div>
                    <div class="product-list-items" data-listing-grid>
                        @forelse ($products as $product)
                            @include('partials.product-card', ['variant' => 'design-two'])
                        @empty
                            <p>No parts match this category yet.</p>
                        @endforelse
                    </div>
                    @if ($lastPage > 1)
                    <div class="brator-pagination-box brator-product-pagination" data-listing-pagination>
                        <nav class="navigation pagination"  aria-label="Posts">
                    @endif
                            <h2 class="screen-reader-text">Posts navigation</h2>
                            {{--
                                Real pagination. The theme shipped "1 2 3 Next" with every
                                href="#_", so a shopper could only ever see page 1 — on a
                                203-result category that hid 92% of the catalogue.

                                Uses the theme's own page-numbers classes, and
                                fullUrlWithQuery so the current filters, sort, view and
                                search term all survive a page change.
                            --}}
                            <div class="nav-links">
                                @if ($page > 1)
                                    <a class="prev page-numbers" href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 0 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"></path></svg> Previous</a>
                                @endif

                                @foreach ($paginationWindow as $number)
                                    @if ($number === null)
                                        <span class="page-numbers dots">&hellip;</span>
                                    @elseif ($number === $page)
                                        <span class="page-numbers current" aria-current="page">{{ $number }}</span>
                                    @else
                                        <a class="page-numbers" href="{{ request()->fullUrlWithQuery(['page' => $number]) }}">{{ $number }}</a>
                                    @endif
                                @endforeach

                                @if ($page < $lastPage)
                                    <a class="next page-numbers" href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">Next <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"></path></svg></a>
                                @endif
                            </div>
                        </nav>
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
