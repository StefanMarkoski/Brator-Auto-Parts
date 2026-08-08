{{-- Ratings, plus the escape hatch. Brands live in the theme's own brand-item
    container, wired in partials/filter-brands. --}}
<div class="brator-filter-item-area">
    <div class="brator-filter-item-title current">
        <h4>Ratings</h4>
    </div>
    <div class="brator-filter-item-content-area">
        {{--
            "Any rating" — THE WAY BACK OUT, and the reason it has to exist.

            Radios have no untick. Once a shopper picked "4 stars & up" the only control on the
            page that could undo it was "Clear all filters, including your car" — so undoing one
            filter meant throwing away the five dropdowns they spent getting to their vehicle.

            value="" is what makes it work, and it needs nothing new on the server:
            ProductFilter::intOrNull() reads '' as null, so minRating comes back null and
            hasAnyNarrowing() therefore does NOT count this as a filter — which is what keeps the
            clear-filters row below from reappearing the moment somebody escapes the rating.
            storefront.js drops empty fields when it builds the URL, so choosing it in place
            produces a URL with no rating parameter at all rather than ?rating=.

            data-auto-submit like every other option: without it this one radio would need the
            Apply button while its four neighbours applied themselves, which reads as broken.

            NO COUNT. The star rows carry a facet count; "any" has no count of its own — facets()
            returns totals for 4/3/2/1 and nothing else — and printing (0) beside the option that
            widens the results would be the exact contradiction between a number and the result it
            produces that this sidebar has already been bitten by. The count span is left off.
        --}}
        <div class="brator-filter-item-content">
            <input type="radio" id="filter-rating-any" name="rating" value=""
                   data-auto-submit
                   @checked($filter->minRating === null) />
            <div class="brator-filter-item-check-box-content">
                <span class="brator-name"><label for="filter-rating-any">Any rating</label></span>
            </div>
        </div>
        @foreach ([4, 3, 2, 1] as $stars)
            {{--
                The <label> is what gives the radio an accessible name: the text used to sit in a
                SIBLING div, so a screen reader announced an unnamed radio and had nothing to read
                out but "radio button, not checked". It goes INSIDE .brator-name so the theme's
                own `.brator-filter-item-check-box-content span` rule still styles the words, and
                the count stays OUTSIDE it — a label wrapping "(12)" would have a screen reader
                read the facet number as part of the option's name.
            --}}
            <div class="brator-filter-item-content">
                <input type="radio" id="filter-rating-{{ $stars }}" name="rating" value="{{ $stars }}"
                       data-auto-submit
                       @checked($filter->minRating === $stars) />
                <div class="brator-filter-item-check-box-content">
                    <span class="brator-name"><label for="filter-rating-{{ $stars }}">{{ $stars }} stars &amp; up</label></span>
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
