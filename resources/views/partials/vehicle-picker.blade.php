{{--
    The theme's Year → Make → Model → Sub Model → Engine picker, made real.

    Server-rendered cascade: each choice posts, the session remembers how far the
    shopper has got, and the page comes back with the next dropdown filled. This is
    deliberate — the theme drives these selects through select2, which replaces the
    native element, so injecting options client-side would fight it. Rendering them
    server-side leaves the theme's own JS completely untouched.

    The select classes are the theme's own, so the styling is identical.
--}}
<form method="post" action="{{ route('vehicle.pick', [], false) }}" class="brator-parts-search-box-form">
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
            <option value="{{ $engine['id'] }}">{{ $engine['label'] }}</option>
        @endforeach
    </select>

    <button type="submit">Search</button>
</form>

{{--
    Escape hatches. Without these a shopper who picked a year the catalogue does not
    cover saw a nearly-empty Make list, no explanation, and no way back — a dead end
    reachable in one click.
--}}
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
