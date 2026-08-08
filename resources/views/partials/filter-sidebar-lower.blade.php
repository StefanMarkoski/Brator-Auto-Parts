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
                       data-auto-submit
                       @checked($filter->minRating === $stars) />
                <div class="brator-filter-item-check-box-content">
                    <span class="brator-name">{{ $stars }} stars &amp; up</span>
                    <span class="brator-count">({{ number_format($facets['ratings'][$stars] ?? 0) }})</span>
                </div>
            </div>
        @endforeach
        @if ($filter->hasAnyNarrowing())
            {{--
                A POST, not a link, and this is the fix for the review's last open finding.

                It used to be a link to the bare listing URL. That clears every filter held in
                the URL — and silently keeps the chosen VEHICLE, which lives in the session. So
                the sidebar came back with nothing ticked while the results stayed narrowed to
                the car, and this control stayed on screen afterwards, making it look as though
                the click had done nothing. The label says "all", so it now clears the car too,
                and says so when there is one.

                The basket is deliberately untouched: it lives in the session as well, and
                losing somebody's shopping to a filter control would be worse than the bug.
            --}}
            {{-- data-clear-filters-row so the in-place update can add and remove this row. It
                 appears and disappears with hasAnyNarrowing(), and syncFilterOptions only ever
                 patched checkbox rows — which nobody noticed, because the price inputs used to
                 make hasAnyNarrowing() permanently true, so this row was always present.
                 Fixing that is what exposed this. A data attribute, not a class, so the theme's
                 stylesheet is still the only thing that styles anything here. --}}
            <div class="brator-filter-item-content" data-clear-filters-row>
                <div class="brator-filter-item-check-box-content">
                    <span class="brator-name">
                        {{--
                            A BUTTON, targeting a form that lives outside the filter form via
                            form="clear-filters". It was a nested <form> at first, which is invalid
                            HTML: the browser dropped the inner one and this button submitted the
                            OUTER filter form as a GET instead — so the page came back with the
                            CSRF token in the query string and the car still selected. Exactly the
                            trap I had already commented on in the admin product editor.

                            The label is built in PHP rather than with an inline @if: written as
                            "filters@if (…)" it rendered VERBATIM, because Blade treats an @ that
                            directly follows a word character as part of an email address.
                        --}}
                        @php($clearLabel = $filter->vehicleVariantId !== null
                            ? 'Clear all filters, including your car'
                            : 'Clear all filters')
                        <button type="submit" form="clear-filters">{{ $clearLabel }}</button>
                    </span>
                </div>
            </div>
        @endif
    </div>
</div>
