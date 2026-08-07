    <!-- Brator categories list three area start -->
    <div class="brator-categories-list-area design-two categories-with-load-more gray-bg">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-md-12">
                    <div class="brator-section-header">
                        <div class="brator-section-header-title">
                            <h2>{{ $section->heading ?? 'Shop by Categories' }}</h2>
                            {{-- The subheading, beside the heading. Its 15px bottom margin is zeroed so it
                                 centres against a 30px h2, and it takes a left gap because the theme drops the
                                 h2's own margin-right inside this container. Inline, so the purchased CSS stays
                                 byte-identical and no class is introduced. --}}
                            @if ($section->subheading)
                                <p style="margin: 0 0 0 14px">{{ $section->subheading }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="brator-categories-list">
                        @foreach ($section->items as $category)
                            <div class="brator-categories-single">
                                <div class="brator-categories-single-img"><a href="{{ route('shop.category', $category->slug, false) }}"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $category->image_path }}" alt="{{ $category->name }}" /></a></div>
                                <div class="brator-categories-single-title">
                                    <p><a href="{{ route('shop.category', $category->slug, false) }}">{{ $category->name }}</a></p>
                                </div>
                                <div class="brator-categories-single-sub"><a href="{{ route('shop.category', $category->slug, false) }}">{{ $category->products_count }} parts</a></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="brator-categories-list-load-more">
                        <button class="brator-categories-more-button">Load More</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Brator categories list three area end -->
