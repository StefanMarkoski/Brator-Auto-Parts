{{--
    The Brands filter, in the theme's own brand-item container so its search box and
    styling are preserved. Brands with a zero count are hidden rather than shown as
    dead options — except one the shopper has already ticked, which must stay visible
    so they can untick it.

    The search box filters the visible list client-side with Alpine; it does not hit
    the server, because the full brand list is already on the page.
--}}
<div class="brator-filter-item-area brand-item" data-filter-scope>
    <div class="brator-filter-item-title current">
        <h4>Brands</h4>
        <button class="ac-trigger" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"></path>
            </svg>
        </button>
    </div>
    <div class="brator-filter-item-content-area">
        <div class="brator-filter-item-content">
            <input type="search" placeholder="Search brand" data-filter-input />
        </div>
        @foreach ($brands as $brand)
            @php($count = $facets['brands'][$brand->slug] ?? 0)
            @continue($count === 0 && ! $filter->hasBrand($brand->slug))
            <div class="brator-filter-item-content" data-filter-label="{{ $brand->name }}">
                {{--
                    The id is derived from the SLUG, never from the loop index.

                    storefront.js's syncFilterOptions() patches this sidebar row by row after an
                    in-place filter change: it pairs old and new rows by name + value and MOVES
                    the survivors with appendChild, so a row's position in the list changes while
                    the node itself is reused. An index-derived id would travel with a moved row
                    and end up naming a different brand — the label would then tick the wrong
                    box. Keyed on the value, the id follows the row wherever it lands.
                --}}
                <input type="checkbox" id="filter-brand-{{ $brand->slug }}" name="brand[]" value="{{ $brand->slug }}"
                       data-auto-submit
                       @checked($filter->hasBrand($brand->slug)) />
                <div class="brator-filter-item-check-box-content">
                    {{--
                        The <label> goes INSIDE brator-name, which the theme already styles, so the
                        purchased CSS still does all the work and no new class is introduced.

                        The count stays OUTSIDE the label on purpose: wrapping "(147)" in it would
                        make a screen reader announce the number as part of the brand's name.
                    --}}
                    <span class="brator-name"><label for="filter-brand-{{ $brand->slug }}">{{ $brand->name }}</label></span>
                    <span class="brator-count">({{ number_format($count) }})</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
