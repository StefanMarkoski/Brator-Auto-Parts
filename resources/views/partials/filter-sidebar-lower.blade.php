{{-- Ratings, plus the escape hatch. Brands live in the theme's own brand-item
    container, wired in partials/filter-brands. --}}
<div class="brator-filter-item-area">
    <div class="brator-filter-item-title current">
        <h4>Ratings</h4>
    </div>
    <div class="brator-filter-item-content-area">
        @foreach ([4, 3, 2, 1] as $stars)
            <div class="brator-filter-item-content">
                <input type="radio" name="rating" value="{{ $stars }}"
                       x-on:change="$el.form.requestSubmit()"
                       @checked($filter->minRating === $stars) />
                <div class="brator-filter-item-check-box-content">
                    <span class="brator-name">{{ $stars }} stars &amp; up</span>
                    <span class="brator-count">({{ number_format($facets['ratings'][$stars] ?? 0) }})</span>
                </div>
            </div>
        @endforeach
        @if ($filter->hasAnyNarrowing())
            <div class="brator-filter-item-content">
                <div class="brator-filter-item-check-box-content">
                    <span class="brator-name"><a href="{{ $category ? route('shop.category', $category->slug, false) : route('search', ['s' => $filter->searchTerm], false) }}">Clear all filters</a></span>
                </div>
            </div>
        @endif
    </div>
</div>
