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
                           x-on:change="$el.form.requestSubmit()"
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
    </div>
    <div class="brator-filter-item-content-area">
        <div class="brator-filter-item-content">
            <div class="brator-filter-item-check-box-content">
                <span class="brator-name">
                    <input type="number" name="price_min" value="{{ $filter->priceMinMinor === null ? '' : (int) ($filter->priceMinMinor / 100) }}"
                           min="{{ $priceBounds['min'] }}" max="{{ $priceBounds['max'] }}" placeholder="{{ $priceBounds['min'] }}" />
                </span>
                <span class="brator-count">
                    <input type="number" name="price_max" value="{{ $filter->priceMaxMinor === null ? '' : (int) ($filter->priceMaxMinor / 100) }}"
                           min="{{ $priceBounds['min'] }}" max="{{ $priceBounds['max'] }}" placeholder="{{ $priceBounds['max'] }}" />
                </span>
            </div>
        </div>
        <div class="brator-filter-item-content">
            <div class="brator-filter-item-check-box-content">
                <span class="brator-name">{{ $priceBounds['min'] }} – {{ $priceBounds['max'] }} ден</span>
                <span class="brator-count"><button type="submit">Apply</button></span>
            </div>
        </div>
    </div>
</div>
