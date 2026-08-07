{{--
    The homepage hero.

    The banner is a full-bleed BACKGROUND with the headings and the vehicle picker sitting on
    top of it, which is how the purchased theme builds it. That is why the pictures are not
    <img> tags and not a Splide slider: either would put the pictures in the document flow and
    the overlay would need new positioning rules, i.e. a design change.

    So the pictures are swapped on the container's own background, which the theme already
    styles `background-size: cover; background-position: center center`. Cover is also the right
    answer to "fill the space but keep the quality": it never squashes a picture to fit, it crops
    the overflow — so the aspect ratio survives and the only cost of a small source image is
    softness, which the admin warns about at the point of adding.

    The dots are the theme's OWN Splide pagination markup, reused. Its CSS is not scoped to a
    slider, so the grey bubbles come for free and no new class is introduced.
--}}
@php
    // The theme's own slider asset, used when staff have added no pictures of their own. An
    // empty hero would be a hole in the design, and this is the file the theme shipped for it.
    $heroImages = collect($section->items ?? [])->pluck('image_path')->filter()->values();
    $first = $heroImages->first() ?? 'assets/images/banner/banner-1.jpg';
@endphp

    <!-- Banner style two start -->
    {{--
        This element carried an inline `position: relative` while the dots were an overlay
        pinned to its bottom edge. They sit in the flow above the search box now, so it is gone
        and the banner is back to exactly the theme's own markup.
    --}}
    <div class="brator-main-banner-area banner-style-two lazyload" data-bg="/{{ $first }}"
        @if ($heroImages->count() > 1)
            {{-- Only when there is something to rotate. One picture stays a plain background
                 with no timer running and no dots to click. --}}
            data-hero-rotate="{{ $heroImages->map(fn (string $path): string => '/'.$path)->values()->toJson() }}"
            data-hero-interval="5000"
            {{-- How long one picture takes to dissolve into the next. Well under the
                 interval, so a picture is fully itself for most of its turn rather than
                 permanently half-way between two. --}}
            data-hero-fade="900"
        @endif
        >
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-md-12">
                    <div class="brator-main-banner-content">
                        <p>#1 Online Marketplace</p>
                        <h2>Car Spares OEM & Atermarkets</h2>
                    </div>
                    <!-- Search by Vehicle -->
                    <div class="brator-parts-search-box-area search-box-with-banner design-two">
                        <div class="brator-parts-search-box-header">
                            <h2>Search by Vehicle</h2>
                            <p>Filter your results by entering your Vehicle to ensure you find the parts that fit.</p>
                        </div>
                        @if ($heroImages->count() > 1)
                            {{--
                                Rendered server-side rather than built in JavaScript, so the dots are
                                in the HTML for anything that does not run scripts and the count is
                                never out of step with the pictures.

                                THE TWO INLINE STYLES, and what each one undoes. Splide's CSS pins
                                this list to the bottom centre of a positioned ancestor as an overlay,
                                and the theme's CSS gives every <li> a 24px grey tile with rounded
                                ends — together, a grey pill sitting on top of the search box. Neither
                                is wanted here: the dots belong above the box, in the flow, bare.

                                Undone inline rather than by adding a class, so the purchased CSS
                                stays byte-identical — and the dot itself, its size, its colour and
                                its dark active state, is still entirely the theme's.
                            --}}
                            <ul class="splide__pagination" data-hero-pagination
                                style="position: static; transform: none; width: 100%; margin-bottom: 14px">
                                @foreach ($heroImages as $index => $path)
                                    <li style="background: none; width: auto; padding: 0">
                                        <button type="button"
                                            class="splide__pagination__page{{ $index === 0 ? ' is-active' : '' }}"
                                            data-hero-page="{{ $index }}"
                                            aria-label="Show picture {{ $index + 1 }} of {{ $heroImages->count() }}"></button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @include('partials.vehicle-picker')
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Banner style two end -->
