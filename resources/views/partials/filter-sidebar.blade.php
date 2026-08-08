{{--
    The listing filter sidebar, built entirely from classes the theme already ships
    (brator-filter-item-*). No new class is introduced — the fidelity test enforces it.

    Everything is one GET form so the URL always describes exactly what is on screen:
    shareable, bookmarkable, and back-button correct. Alpine submits on change so it
    still feels immediate; without JS it degrades to the Apply button.

    Counts come from FilteredProductsQuery::facets(), each group counted with ITSELF
    lifted out of the filter — a shopper who has ticked "OEM" wants to know how many
    Aftermarket parts exist, not zero.

    @param  \App\Domain\Catalog\DTOs\ProductFilter  $filter
--}}
@php($vatNote = null)

@if ($category && $category->children->isNotEmpty())
    <div class="brator-filter-item-area">
        <div class="brator-filter-item-title current">
            <h4>Categories</h4>
        </div>
        <div class="brator-filter-item-content-area">
            @foreach ($category->children as $child)
                <div class="brator-filter-item-content">
                    <div class="brator-filter-item-check-box-content">
                        <span class="brator-name"><a href="{{ route('shop.category', $child->slug, false) }}">{{ $child->name }}</a></span>
                        <span class="brator-count">({{ $child->products_count }})</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@foreach ($filterGroups as $group)
    {{--
        A group with no options is skipped, INCLUDING a range one.

        The condition used to keep range groups on the grounds that a range renders a control
        rather than a list — but no range control is rendered anywhere in this loop, so
        Diameter, Width and Offset (the three seeded `range` attributes, attached to every
        department) each drew a filter heading with nothing whatsoever underneath it. Three
        empty boxes in the sidebar of every listing page.

        When a real min/max control is built for numeric attributes, the exception comes back
        with the control — not before it.
    --}}
    @continue($group['options'] === [])
    <div class="brator-filter-item-area">
        <div class="brator-filter-item-title current">
            <h4>{{ $group['label'] }}@if ($group['unit']) ({{ $group['unit'] }})@endif</h4>
        </div>
        <div class="brator-filter-item-content-area">
            @foreach ($group['options'] as $option)
                @php($count = $facets['attributes'][$group['code']][$option['value']] ?? 0)
                {{--
                    The id is derived from the group code and the VALUE, never from the loop index.

                    storefront.js's syncFilterOptions() patches this sidebar row by row after an
                    in-place filter change: it pairs old and new rows by name + value and MOVES the
                    survivors with appendChild, so a row's position changes while the node itself is
                    reused. An index-derived id would travel with a moved row and end up naming a
                    different option — the label would then tick the wrong box. Keyed on the value,
                    the id follows the row wherever it lands.

                    The group code is in the id because the same value can legitimately appear in two
                    groups, and two inputs sharing an id would send both labels to whichever came
                    first in the document.
                --}}
                @php($optionId = 'filter-attr-'.$group['code'].'-'.Str::slug($option['value']))
                <div class="brator-filter-item-content">
                    <input type="checkbox"
                           id="{{ $optionId }}"
                           name="attr[{{ $group['code'] }}][]"
                           value="{{ $option['value'] }}"
                           data-auto-submit
                           @checked($filter->hasAttribute($group['code'], $option['value']))
                           @disabled($count === 0 && ! $filter->hasAttribute($group['code'], $option['value'])) />
                    <div class="brator-filter-item-check-box-content">
                        {{--
                            The <label> goes INSIDE brator-name, which the theme already styles (and
                            which carries the swatch's inline border), so the purchased CSS still does
                            all the work and no new class is introduced.

                            The count stays OUTSIDE the label on purpose: wrapping "(147)" in it would
                            make a screen reader announce the number as part of the option's name.
                        --}}
                        <span class="brator-name" @if ($option['swatch']) style="border-left:12px solid {{ $option['swatch'] }};padding-left:6px" @endif><label for="{{ $optionId }}">{{ $option['value'] }}</label></span>
                        <span class="brator-count">({{ number_format($count) }})</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endforeach

<div class="brator-filter-item-area">
    <div class="brator-filter-item-title current">
        <h4>Price</h4>
        <button class="ac-trigger" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"></path>
            </svg>
        </button>
    </div>
    <div class="brator-filter-item-content-area">
        {{--
            The theme's OWN price markup: two inputs in brator-rang-item-input, and the
            noUiSlider mount it already styles and already initialises.

            The first attempt put bare number inputs in this block. The theme's CSS expects
            a slider here, so the two inputs rendered stacked on the identical rectangle and
            the control could not be operated with a mouse at all. Driving the real slider
            needs no new CSS and looks exactly as designed.

            The inputs still carry the values, so the form submits a usable range with
            JavaScript off.
        --}}
        {{--
            EMPTY UNLESS A PRICE FILTER IS ACTUALLY SET. This is the fix for a trap.

            These inputs used to fall back to the current bounds when no price filter was
            applied — and they live inside this GET form, which the in-place filtering
            serialises whole. So every submit carried a price_min and a price_max, which made
            ProductFilter::hasAnyNarrowing() true forever: "Clear all filters, including your
            car" never went away again once you had touched anything.

            Worse, the bounds are computed from the FILTERED set. Tick "Gates", and the inputs
            render Gates's own cheapest and dearest. Untick it, and the request goes out
            carrying Gates's price band against the whole catalogue — so removing your only
            filter left you looking at a narrower list than before, with the slider visibly
            not at its ends and nothing on screen explaining why.

            An empty value submits as '' and ProductFilter::minor() reads that as null, so a
            no-JavaScript submit means "no price filter" rather than "this exact band".
        --}}
        <div class="brator-rang-item-input">
            <div class="brator-rang-item-input-single">
                <input type="number" name="price_min" data-price-min
                       value="{{ $filter->priceMinMinor === null ? '' : (int) ($filter->priceMinMinor / 100) }}"
                       placeholder="{{ $priceBounds['min'] }}"
                       min="{{ $priceBounds['min'] }}" max="{{ $priceBounds['max'] }}" />
                <div class="brator-rang-item-input-single-text">{{ config('shop.currency_symbol') }} min</div>
            </div>
            <div class="brator-rang-item-input-single">
                <input type="number" name="price_max" data-price-max
                       value="{{ $filter->priceMaxMinor === null ? '' : (int) ($filter->priceMaxMinor / 100) }}"
                       placeholder="{{ $priceBounds['max'] }}"
                       min="{{ $priceBounds['min'] }}" max="{{ $priceBounds['max'] }}" />
                <div class="brator-rang-item-input-single-text">{{ config('shop.currency_symbol') }} max</div>
            </div>
            <div class="brator-rang-item-input-single-btn">
                <button type="submit">Go</button>
            </div>
        </div>
        <div class="brator-rang-item-slider">
            <div id="brator-rang-item-slider-nou"
                 data-price-slider
                 data-price-floor="{{ $priceBounds['min'] }}"
                 data-price-ceiling="{{ $priceBounds['max'] }}"
                 data-currency="{{ config('shop.currency_symbol') }}"></div>
            <div class="brator-filter-item-check-box-content">
                <span class="brator-name" data-price-readout>{{ number_format($priceBounds['min']) }} - {{ number_format($priceBounds['max']) }} {{ config('shop.currency_symbol') }}</span>
            </div>
        </div>
    </div>
</div>
