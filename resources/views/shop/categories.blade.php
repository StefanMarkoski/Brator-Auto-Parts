@extends('layouts.shop')

@section('title', 'Brator Auto Parts')

@section('content')
    <!-- Blog post grid start-->
    <div class="brator-banner-slider-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-md-12">
                    <div class="brator-banner-area design-four lazyload" data-bg="/assets/images/slider/slide-03.jpg">
                        <div class="brator-banner-content">
                            <h2><a href="{{ route('shop.categories', [], false) }}">Shop by category</a></h2>
                            <p>{{ number_format($totalProducts) }} parts across {{ $categories->count() }} categories. Narrow by vehicle, brand, price or specification once you are inside a category.</p>
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
    <!-- Blog post grid start-->
    <section class="brator-breadcrumb-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="brator-parts-search-box-area design-two">
                        <div class="brator-parts-search-box-header">
                            <h2>Search by Vehicle</h2>
                            <p>Filter your results by entering your Vehicle to ensure you find the parts that fit.</p>
                        </div>
                        @include('partials.vehicle-picker')
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Blog post grid end-->
    <!-- Blog post grid start-->
    <div class="brator-categories-list-area design-two">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-md-12">
                    <div class="brator-section-header">
                        <div class="brator-section-header-title">
                            <h2>Sub Categories</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="brator-categories-list">
                        @foreach ($categories as $category)
                            <div class="brator-categories-single">
                                <div class="brator-categories-single-img"><a href="{{ route('shop.category', $category->slug, false) }}"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $category->image_path }}" alt="{{ $category->name }}" /></a></div>
                                <div class="brator-categories-single-title">
                                    <p><a href="{{ route('shop.category', $category->slug, false) }}">{{ $category->name }}</a></p>
                                </div>
                                <div class="brator-categories-single-sub">
                                    @foreach ($category->children->take(4) as $child)
                                        <a href="{{ route('shop.category', $child->slug, false) }}">{{ $child->name }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Blog post grid end-->
    <!-- bread start-->
    <div class="brator-makes-list-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-md-12">
                    <div class="brator-section-header">
                        <div class="brator-section-header-title">
                            <h2>Featured Makes</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="brator-makes-list">
                        @foreach ($makes as $navMake)
                            <div class="brator-makes-list-single">
                                <a href="{{ route('vehicle.by-make', $navMake['slug'], false) }}">
                                    <span>{{ $navMake['name'] }}</span>
                                    <svg class="bi bi-chevron-right" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"></path>
                                    </svg>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="brator-makes-list-view-more">
                        <button> <span><b>view more</b>
                                <svg class="bi bi-chevron-down" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"></path>
                                </svg></span></button>
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
    <div class="brator-brand-slider design-two">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-md-12">
                    <div class="brator-section-header">
                        <h2>Featured Brands</h2>
                    </div>
                    <div class="brator-brand design-one splide js-splide p-splide" data-splide='{"pagination":false,"type":"loop","perPage":5,"perMove":"1","gap":30, "breakpoints":{ "520" :{ "perPage": "1" },"746" :{ "perPage": "2" }, "767" :{ "perPage" : "2" }, "1090":{ "perPage" : "3" }, "1366":{ "perPage" : "4" }, "1500":{ "perPage" : "4" }, "1920":{ "perPage" : "5" }}}'>
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
                                <div class="brator-brand-img splide__slide"><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/brand/brand-10.png" alt="logo" /></a><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/brand/brand-09.png" alt="logo" /></a></div>
                                <div class="brator-brand-img splide__slide"><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/brand/brand-08.png" alt="logo" /></a><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/brand/brand-07.png" alt="logo" /></a></div>
                                <div class="brator-brand-img splide__slide"><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/brand/brand-11.png" alt="logo" /></a><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/brand/brand-12.png" alt="logo" /></a></div>
                                <div class="brator-brand-img splide__slide"><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/brand/brand-13.png" alt="logo" /></a><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/brand/brand-14.png" alt="logo" /></a></div>
                                <div class="brator-brand-img splide__slide"><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/brand/brand-16.png" alt="logo" /></a><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/brand/brand-15.png" alt="logo" /></a></div>
                                <div class="brator-brand-img splide__slide"><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/brand/brand-17.png" alt="logo" /></a><a href="#_"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/assets/images/brand/brand-18.png" alt="logo" /></a></div>
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
                    {{-- The carousel classes are CONDITIONAL. The theme mounts Splide on every element with
                         the class "splide", and Splide on an EMPTY list still initialises: with
                         type:loop it computes a clone offset from nothing and translates the list
                         a few hundred pixels sideways, so the "nothing here yet" line appeared
                         shoved off to one side. No items, no carousel — just the message. --}}
                    <div class="brator-product-slider @if (count($featured ?? [])) splide js-splide p-splide @endif" data-splide='{"pagination":false,"type":"loop","perPage":5,"perMove":"1","gap":30, "breakpoints":{ "520" :{ "perPage": "1" },"746" :{ "perPage": "2" }, "767" :{ "perPage" : "2" }, "1090":{ "perPage" : "3" }, "1366":{ "perPage" : "4" }, "1500":{ "perPage" : "4" }, "1920":{ "perPage" : "5" }}}'>
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
                                @forelse ($featured as $featuredProduct)
                                    @include('partials.product-card', ['product' => $featuredProduct])
                                @empty
                                    <p>No best sellers yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- bread end-->
    <!-- bread start-->
    <div class="brator-more-text-area design-one">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="brator-more-text-content">
                        <p>Pick a category to start, or choose your vehicle to see only parts that fit it.</p>
                        <p class="disable">Every part is matched to the vehicles it fits, so you only see what will actually bolt on.</p>
                    </div>
                    <div class="brator-more-text-view-more">
                        <button> <span>view more</span>
                            <svg class="bi bi-chevron-down" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- bread end-->
@endsection
