    <!-- Brator whats hot area start -->
    <div class="brator-offer-slider-area brator-whats-hot-area">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="brator-section-header">
                        {{--
                            THE SUBHEADING NOW RENDERS. It was being saved and shown nowhere — the
                            editor offered a Subheading field that every single section ignored, so
                            typing one looked like a save that had silently failed.

                            It goes inside brator-section-header-title, which the theme styles as a
                            flex-wrap row precisely so it can carry a second element beside the
                            heading (its other use holds a countdown). Dropped straight into
                            brator-section-header it would be flung to the far right instead, because
                            that container is justify-content: space-between.

                            No new class — both of these ship with the theme.
                        --}}
                        <div class="brator-section-header-title">
                            <h2>{{ $section->heading ?? "What's Hot" }}</h2>
                            @if ($section->subheading)
                                <p style="margin: 0 0 0 14px">{{ $section->subheading }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="brator-offer-slider brator-whats-hot-slider splide js-splide p-splide" data-splide='{"autoplay":false, "arrows":true,"pagination":false,"type":"loop","perPage":4,"perMove":"1","gap":15, "breakpoints":{ "520" :{ "perPage": "1" },"767" :{ "perPage": "1" }, "991" :{ "perPage" : "2" }, "1090":{ "perPage" : "2" }, "1366":{ "perPage" : "3" }, "1500":{ "perPage" : "4" }, "1920":{ "perPage" : "4" }}}'>
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
                                <!-- single item -->
                                @foreach ($section->items as $banner)
                                    <div class="splide__slide">
                                        <div class="brator-hot-single-box brator-hot-box-design-{{ ['one', 'two', 'three', 'four'][$loop->index % 4] }} lazyload" data-bg="/{{ $banner->image_path }}">
                                            <div class="brator-hot-box-content">
                                                <div class="brator-hot-box-text">
                                                    <p>{{ $banner->subtitle }}</p>
                                                    <h2>{!! nl2br(e($banner->title)) !!}</h2>
                                                </div>
                                                <div class="brator-hot-box-button">
                                                    <a href="{{ $banner->link_url ?? '#_' }}">{{ $banner->link_label ?? 'Shop Now' }}</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Brator whats hot area end -->
    <div class="brator-plan-pixel-area">
        <div class="row">
            <div class="container-xxxl container-xxl container">
                <div class="col-12">
                    <div class="plan-pixel-area"></div>
                </div>
            </div>
        </div>
    </div>
