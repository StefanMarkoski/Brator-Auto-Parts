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
    @continue($group['options'] === [] && $group['widget'] !== 'range')
    <div class="brator-filter-item-area">
        <div class="brator-filter-item-title current">
            <h4>{{ $group['label'] }}@if ($group['unit']) ({{ $group['unit'] }})@endif</h4>
        </div>
        <div class="brator-filter-item-content-area">
            @foreach ($group['options'] as $option)
                @php($count = $facets['attributes'][$group['code']][$option['value']] ?? 0)
                <div class="brator-filter-item-content">
                    <input type="checkbox"
                           name="attr[{{ $group['code'] }}][]"
                           value="{{ $option['value'] }}"
                           data-auto-submit
                           @checked($filter->hasAttribute($group['code'], $option['value']))
                           @disabled($count === 0 && ! $filter->hasAttribute($group['code'], $option['value'])) />
                    <div class="brator-filter-item-check-box-content">
                        <span class="brator-name" @if ($option['swatch']) style="border-left:12px solid {{ $option['swatch'] }};padding-left:6px" @endif>{{ $option['value'] }}</span>
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
        <div class="brator-rang-item-input">
            <div class="brator-rang-item-input-single">
                <input type="number" name="price_min" data-price-min
                       value="{{ $filter->priceMinMinor === null ? $priceBounds['min'] : (int) ($filter->priceMinMinor / 100) }}"
                       min="{{ $priceBounds['min'] }}" max="{{ $priceBounds['max'] }}" />
                <div class="brator-rang-item-input-single-text">{{ config('shop.currency_symbol') }} min</div>
            </div>
            <div class="brator-rang-item-input-single">
                <input type="number" name="price_max" data-price-max
                       value="{{ $filter->priceMaxMinor === null ? $priceBounds['max'] : (int) ($filter->priceMaxMinor / 100) }}"
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
