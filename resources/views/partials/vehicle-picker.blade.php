{{--
    The theme's Year → Make → Model → Sub Model → Engine picker, made real.

    THE CASCADE IS STILL SERVER-RENDERED, and that has not changed: each choice posts,
    the session remembers how far the shopper has got, and the next dropdown comes back
    filled. Every rule about which options are available lives in one place, on the
    server, and the form works with JavaScript switched off.

    What changed is that the round trip no longer reloads the document. storefront.js
    posts the same form, takes the picker out of the response and copies each dropdown's
    OPTIONS into the dropdown already on the page — so choosing a Year no longer throws
    the shopper back to the top of the homepage. The selects themselves are never
    replaced, because the theme drives them through select2.
--}}
<form method="post" action="{{ route('vehicle.pick', [], false) }}" class="brator-parts-search-box-form"
    data-vehicle-picker>
    @csrf
    <input type="hidden" name="redirect_to" value="{{ route('shop.categories', [], false) }}" />

    <select class="select-year-parts brator-select-active" name="year" data-auto-submit>
        <option value="">Year</option>
        @foreach ($vehiclePicker['years'] as $year)
            <option value="{{ $year }}" @selected($vehiclePicker['state']['year'] === $year)>{{ $year }}</option>
        @endforeach
    </select>

    <select class="select-make-parts brator-select-active" name="make" data-auto-submit>
        <option value="">Make</option>
        @foreach ($vehiclePicker['makes'] as $make)
            <option value="{{ $make['id'] }}" @selected($vehiclePicker['state']['make'] === $make['id'])>{{ $make['name'] }}</option>
        @endforeach
    </select>

    <select class="select-model-parts brator-select-active" name="model"
            @disabled($vehiclePicker['models'] === []) data-auto-submit>
        <option value="">Model</option>
        @foreach ($vehiclePicker['models'] as $model)
            <option value="{{ $model['id'] }}" @selected($vehiclePicker['state']['model'] === $model['id'])>{{ $model['name'] }}</option>
        @endforeach
    </select>

    <select class="select-sub-model-parts brator-select-active" name="name"
            @disabled($vehiclePicker['names'] === []) data-auto-submit>
        <option value="">Sub Model</option>
        @foreach ($vehiclePicker['names'] as $variantName)
            <option value="{{ $variantName }}" @selected($vehiclePicker['state']['name'] === $variantName)>{{ $variantName }}</option>
        @endforeach
    </select>

    <select class="select-engine-parts brator-select-active" name="vehicle_variant_id"
            @disabled($vehiclePicker['engines'] === []) data-auto-submit>
        <option value="">Engine</option>
        @foreach ($vehiclePicker['engines'] as $engine)
            {{-- @selected was missing here, so the Engine box came back empty on any real
                 page load even though a vehicle WAS chosen — the one level of the cascade
                 that forgot what the shopper had picked. --}}
            <option value="{{ $engine['id'] }}"
                @selected($vehiclePicker['variant'] === $engine['id'])>{{ $engine['label'] }}</option>
        @endforeach
    </select>

    <button type="submit">Search</button>
</form>

{{--
    Escape hatches. Without these a shopper who picked a year the catalogue does not
    cover saw a nearly-empty Make list, no explanation, and no way back — a dead end
    reachable in one click.

    Both depend on how far the cascade has got, so they are kept in one always-present
    container the in-place update can refresh wholesale. It holds nothing the theme has
    bound to — a message and a plain form — so unlike the selects it is safe to replace.
    The container is an unclassed div and renders empty when neither applies, which
    occupies no space.
--}}
<div data-vehicle-extras>
    @if ($vehiclePicker['state']['year'] !== null && $vehiclePicker['makes'] === [])
        <div class="brator-current-vehicle-content">
            <p>No vehicles in the catalogue for {{ $vehiclePicker['state']['year'] }}. Try another year, or start again.</p>
        </div>
    @endif

    @if (array_filter($vehiclePicker['state'], fn ($v) => $v !== null) !== [])
        <form method="post" action="{{ route('vehicle.clear', [], false) }}">
            @csrf
            <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}" />
            <button type="submit">Start again</button>
        </form>
    @endif
</div>
