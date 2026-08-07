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

    {{--
        "Start again" lives INSIDE the search box, next to Search.

        It used to be a button in its own little form under the rectangle, which meant the
        browser's default grey button sitting on top of the hero image — the theme styles
        buttons per section, so a button outside its section gets no styling at all. In here
        the theme's own rule (.brator-parts-search-box-area.design-two ... form button) gives
        it exactly the Search button's look, with no CSS of ours involved.

        formaction, because HTML forms cannot nest: the button posts the same form to the
        clear route instead. reset_redirect_to travels with it so clearing keeps the shopper
        on the page they are on, while Search still goes to the listing.

        Always rendered and hidden with the theme's d-none when there is nothing to clear —
        the in-place cascade only copies <option>s between pickers, so a button that had to
        be created on the fly would not appear until a reload.
    --}}
    {{--
        getRequestUri, not fullUrl. The old button posted the absolute URL, which SafeRedirect
        refuses on sight — anything with a scheme or host is not a bare path — so it silently
        fell back to /shop and "Start again" quietly moved the shopper off the page they were
        on. Found by clicking it, not by reading it: both spellings look right.
    --}}
    <input type="hidden" name="reset_redirect_to" value="{{ request()->getRequestUri() }}" />

    <button type="submit">Search</button>

    {{-- After Search, not before: below 1200px the theme lays the box out three to a row, and
         in this order Search completes the grid while "Start again" takes the short last row.
         Ahead of it, Search was the one left stranded on its own line. --}}
    <button type="submit" formaction="{{ route('vehicle.clear', [], false) }}"
        @class(['d-none' => ! $vehiclePicker['hasSelection']]) data-vehicle-reset>Start again</button>
</form>

{{--
    The dead-end note. Without it a shopper who picked a year the catalogue does not
    cover saw a nearly-empty Make list, no explanation, and no way back — a dead end
    reachable in one click.

    It depends on how far the cascade has got, so it lives in an always-present container
    the in-place update refreshes wholesale. The container holds nothing the theme has
    bound to — just a message — so unlike the selects it is safe to replace. It is an
    unclassed div and renders empty when the note does not apply, which occupies no space.

    "Start again" used to live here too. It is inside the search box now, where the theme
    styles it.
--}}
<div data-vehicle-extras>
    @if ($vehiclePicker['state']['year'] !== null && $vehiclePicker['makes'] === [])
        <div class="brator-current-vehicle-content">
            <p>No vehicles in the catalogue for {{ $vehiclePicker['state']['year'] }}. Try another year, or start again.</p>
        </div>
    @endif
</div>
