@extends('admin.layouts.admin')
@section('title', 'Vehicles')
@section('heading', 'Vehicles')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Vehicles" />

    @if (session('status'))
        <x-admin.alert variant="success" class="mb-6">{{ session('status') }}</x-admin.alert>
    @endif

    @if (session('error'))
        <x-admin.alert variant="error" title="Nothing was added" class="mb-6">{{ session('error') }}</x-admin.alert>
    @endif

    @if ($errors->any())
        <x-admin.alert variant="error" title="Check the form" class="mb-6">{{ $errors->first() }}</x-admin.alert>
    @endif

    {{--
        THE POINT OF THIS PANEL, and why the numbers are here rather than in a comment: the
        shop's Year → Make → Model → Sub Model → Engine filter is a live query over these rows.
        These five counts are read the same way that filter reads them, so adding a vehicle below
        and watching them move is the demonstration. Nothing about the filter is written in code.
    --}}
    <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="text-base font-medium text-gray-800 dark:text-white/90">
                    What the shop's vehicle filter is offering right now
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Read live from the rows below — the homepage filter has no fixed list of its own.
                    Add a vehicle and these change on the next page load.
                </p>
            </div>

            <a href="{{ route('home', [], false) }}" target="_blank"
                class="text-sm font-medium text-brand-500 hover:underline">Open the shop's filter &rarr;</a>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
            @php
                $tiles = [
                    ['Years', $counts['years'] === 0
                        ? '—'
                        : $counts['first_year'].'–'.$counts['last_year'], $counts['years'].' in the dropdown'],
                    ['Makes', number_format($counts['makes']), 'with a vehicle under them'],
                    ['Models', number_format($counts['models']), 'with a vehicle under them'],
                    ['Vehicles', number_format($counts['vehicles']), 'sub model + engine rows'],
                    ['Switched off', number_format($counts['hidden']), 'not in the filter'],
                ];
            @endphp

            @foreach ($tiles as [$label, $value, $note])
                <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                    <p class="text-xs uppercase text-gray-400">{{ $label }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">{{ $value }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ $note }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-admin.component-card title="Vehicles"
                :desc="number_format($vehicles->total()).' sub model and engine combinations. Which parts fit a car is set on each part\'s own screen — the Parts number opens that list.'">

                <form method="get" action="{{ route('admin.vehicles.index', [], false) }}" class="mb-5 flex gap-3">
                    <x-admin.input name="q" :value="$search" placeholder="Search make, model, sub model or engine" />
                    <x-admin.button type="submit" variant="outline">Search</x-admin.button>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-400">
                            <tr>
                                <th class="pb-3 pr-4">Make</th>
                                <th class="pb-3 pr-4">Model</th>
                                <th class="pb-3 pr-4">Sub model</th>
                                <th class="pb-3 pr-4">Engine</th>
                                <th class="pb-3 pr-4">Years</th>
                                <th class="pb-3 pr-4 text-right">Parts</th>
                                <th class="pb-3 pr-4">Status</th>
                                <th class="pb-3 pr-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($vehicles as $vehicle)
                                {{-- The row just added is marked, so it is findable on a list of
                                     hundreds without reading every line. --}}
                                <tr @class(['bg-brand-50/60 dark:bg-brand-500/10' => (int) session('added_variant') === (int) $vehicle->id])>
                                    <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">{{ $vehicle->make }}</td>
                                    <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">{{ $vehicle->model }}</td>
                                    <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">{{ $vehicle->name }}</td>
                                    <td class="py-3 pr-4 text-gray-400">
                                        {{ $vehicle->engine_code ?: '—' }}
                                        @if ($vehicle->power_kw)
                                            <span class="text-xs">{{ $vehicle->power_kw }}kW</span>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-4 text-gray-400">
                                        {{ $vehicle->year_from }} – {{ $vehicle->year_to ?? 'present' }}
                                    </td>
                                    {{-- The way through to the parts. Fitment is set per part on
                                         the part's own screen, so this lists them rather than
                                         offering to assign in bulk from here. --}}
                                    <td class="py-3 pr-4 text-right">
                                        @if ((int) $vehicle->parts_count > 0)
                                            <a href="{{ route('admin.products.index', ['fits' => $vehicle->id], false) }}"
                                                class="text-gray-500 hover:text-brand-500 hover:underline">
                                                {{ number_format((int) $vehicle->parts_count) }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-4">
                                        {{-- "Active", not "In the filter": the badge capitalises
                                             every word, and "In The Filter" reads like a mistake. --}}
                                        <x-admin.badge size="sm" :color="$vehicle->is_active ? 'success' : 'light'">
                                            {{ $vehicle->is_active ? 'Active' : 'Hidden' }}
                                        </x-admin.badge>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <div class="flex items-center justify-end gap-3">
                                            {{-- Its own form, because it is its own decision. A row
                                                 of buttons sharing one form posts every field. --}}
                                            <form method="post"
                                                action="{{ route('admin.vehicles.update', $vehicle->id, false) }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="is_active"
                                                    value="{{ $vehicle->is_active ? '0' : '1' }}" />
                                                <button type="submit"
                                                    class="text-sm font-medium text-brand-500 hover:underline">
                                                    {{ $vehicle->is_active ? 'Hide' : 'Show' }}
                                                </button>
                                            </form>

                                            <x-admin.confirm-action
                                                :action="route('admin.vehicles.destroy', $vehicle->id, false)"
                                                method="DELETE"
                                                label="Delete"
                                                trigger-class="text-sm font-medium text-error-600 hover:underline dark:text-error-400"
                                                :disabled="(int) $vehicle->parts_count > 0"
                                                :disabled-reason="number_format((int) $vehicle->parts_count).' parts are recorded as fitting this vehicle. Hide it instead.'"
                                                :title="'Delete '.$vehicle->make.' '.$vehicle->model.' '.$vehicle->name.'?'"
                                                message="It disappears from the shop's vehicle filter. No parts are affected — this vehicle has none recorded against it."
                                                confirm="Yes, delete it" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-6 text-center text-gray-400">
                                        No vehicles match that search.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">{{ $vehicles->links() }}</div>
            </x-admin.component-card>
        </div>

        {{--
            "Choose one or type a new one", for the make and the model both.

            Nobody should have to create a make, save, create a model, save, and only then reach
            the vehicle — that is three screens for one thought. The select carries every make
            that exists plus one option that reveals a text box, and the model list follows the
            chosen make. Typing a make that already exists REUSES it; the server decides that,
            not this script, so it holds however the form is posted.

            Alpine only ever HIDES the "new" boxes. If the admin bundle failed to load, both the
            select and the text box are visible and the server prefers whichever was filled in —
            the form still works, it just looks busier.
        --}}
        <div x-data="{
                makes: {{ Js::from($makes) }},
                makeId: '{{ old('make_id') }}',
                modelId: '{{ old('model_id') }}',
                get models() {
                    const make = this.makes.find(m => String(m.id) === String(this.makeId));
                    return make ? make.models : [];
                },
                get newMake() { return this.makeId === 'new'; },
                get chosenMake() { return this.makeId !== ''; },
                /* Nothing for the model until a make is settled: a model typed with no make is a
                   row that cannot be filed, and the server can only answer that with an error. */
                get newModel() {
                    return this.chosenMake
                        && (this.newMake || this.modelId === 'new' || this.models.length === 0);
                },
                onMake() { this.modelId = ''; },
            }">

            <x-admin.component-card title="Add a vehicle"
                desc="Pick a make and model, or type a new one — you do not have to create them separately first.">

                <form method="post" action="{{ route('admin.vehicles.store', [], false) }}" class="space-y-5">
                    @csrf

                    <x-admin.field label="Make" name="make_name" :required="true">
                        <select name="make_id" x-model="makeId" x-on:change="onMake()"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Choose a make…</option>
                            @foreach ($makes as $make)
                                <option value="{{ $make['id'] }}" @selected(old('make_id') == $make['id'])>
                                    {{ $make['name'] }}{{ $make['is_active'] ? '' : ' (hidden)' }}
                                </option>
                            @endforeach
                            {{-- "new" rather than an empty value, so the choice is explicit: the
                                 controller turns it into nothing and the typed name is used. --}}
                            <option value="new" @selected(old('make_id') === 'new')>+ A make that is not listed</option>
                        </select>

                        <div x-show="newMake" x-cloak class="mt-3">
                            <x-admin.input name="make_name" :value="old('make_name')"
                                placeholder="Tesla" x-bind:required="newMake" />
                        </div>
                    </x-admin.field>

                    <x-admin.field label="Model" name="model_name" :required="true">
                        <select name="model_id" x-model="modelId" x-show="!newMake && models.length"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Choose a model…</option>
                            <template x-for="model in models" :key="model.id">
                                <option :value="model.id" x-text="model.name"
                                    :selected="String(model.id) === String(modelId)"></option>
                            </template>
                            <option value="new">+ A model that is not listed</option>
                        </select>

                        <div x-show="newModel" x-cloak class="mt-3">
                            <x-admin.input name="model_name" :value="old('model_name')"
                                placeholder="Model 3" x-bind:required="newModel" />
                        </div>

                        <p x-show="!chosenMake" x-cloak class="text-sm text-gray-400">
                            Choose a make above first.
                        </p>
                    </x-admin.field>

                    <x-admin.field label="Sub model" name="name" :required="true"
                        hint="What the shopper picks in the fourth dropdown, e.g. Long Range or 2.0 TDI.">
                        <x-admin.input name="name" :value="old('name')" required placeholder="Long Range" />
                    </x-admin.field>

                    <div class="grid grid-cols-2 gap-4">
                        <x-admin.field label="Engine" name="engine_code"
                            hint="Shown in the fifth dropdown.">
                            <x-admin.input name="engine_code" :value="old('engine_code')" placeholder="3D0" />
                        </x-admin.field>

                        <x-admin.field label="Power (kW)" name="power_kw">
                            <x-admin.input type="number" min="1" max="2000" name="power_kw"
                                :value="old('power_kw')" placeholder="248" />
                        </x-admin.field>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-admin.field label="First year" name="year_from" :required="true">
                            <x-admin.input type="number" name="year_from" required
                                min="{{ $yearFloor }}" max="{{ $yearCeiling }}"
                                :value="old('year_from', (int) date('Y'))" />
                        </x-admin.field>

                        <x-admin.field label="Last year" name="year_to"
                            hint="Leave blank if it is still made — the filter reads that as “present”.">
                            <x-admin.input type="number" name="year_to"
                                min="{{ $yearFloor }}" max="{{ $yearCeiling }}" :value="old('year_to')" />
                        </x-admin.field>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-admin.field label="Fuel" name="fuel_type">
                            <x-admin.select name="fuel_type"
                                :options="collect($fuelTypes)->mapWithKeys(fn ($f) => [$f->value => ucfirst($f->value)])->all()"
                                :selected="old('fuel_type', 'petrol')" />
                        </x-admin.field>

                        <x-admin.field label="Engine size (cc)" name="engine_cc">
                            <x-admin.input type="number" min="1" max="20000" name="engine_cc"
                                :value="old('engine_cc')" placeholder="1968" />
                        </x-admin.field>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400">
                        <input type="hidden" name="is_active" value="0" />
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))
                            class="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700" />
                        Show it in the shop's filter
                    </label>

                    <x-admin.button type="submit">Add vehicle</x-admin.button>
                </form>
            </x-admin.component-card>
        </div>
    </div>
@endsection
