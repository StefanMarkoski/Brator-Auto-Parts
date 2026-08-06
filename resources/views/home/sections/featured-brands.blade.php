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
                        {{-- The brand's name when there is no logo: the seeded values were other
                             companies' logos, so an empty slot is more honest than a wrong mark. --}}
                        <div class="brator-brand-img">
                            @if ($brand->logo_path)
                                <a href="{{ route('search', ['brand' => [$brand->slug]], false) }}"><img class="lazyload" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="/{{ $brand->logo_path }}" alt="{{ $brand->name }}" /></a>
                            @else
                                <a href="{{ route('search', ['brand' => [$brand->slug]], false) }}">{{ $brand->name }}</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <!-- Brator featured brands start -->
