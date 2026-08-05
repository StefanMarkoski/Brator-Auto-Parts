    <!-- Brator featured brands start -->
    <div class="brator-brand-item-area design-three">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-md-12">
                    <div class="brator-section-header">
                        <div class="brator-section-header-title">
                            <h2>{{ $section->heading ?? 'Featured Brands' }}</h2>
                        </div>
                    </div>
                </div>
                @foreach ($section->items as $brand)
                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 col-6">
                        <div class="brator-brand-img"><a href="{{ route('shop.categories') }}?brand={{ $brand->slug }}"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="  data-src="/{{ $brand->logo_path }}" alt="{{ $brand->name }}" /></a></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <!-- Brator featured brands start -->
