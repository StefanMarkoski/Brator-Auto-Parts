@props(['chosen' => [], 'name' => 'fitment_variant_ids'])

{{--
    Fitment picker — which cars a part fits.

    NOT a multi-select of every vehicle. There are 82 engine variants in this catalogue and a
    real vehicle tree is tens of thousands; one list of all of them is unusable, and a staff
    member hunting for a car thinks in the same order a shopper does. So it is the same cascade
    as the storefront's — Year, Make, Model, Sub Model, Engine — and choosing an engine adds
    that one car to the list below.

    Year is OPTIONAL and only ever narrows: a part that fits a model across its whole life
    should not need a year picked first.

    Down to ENGINE level, not model, because the engine really does decide the part. A more
    powerful engine gets bigger brakes — the same model with a 1.4 and a 2.0 takes different
    discs — so "fits a Golf V" is not a fact a parts shop can act on. Adding every engine of a
    sub model at once is therefore a separate, deliberate click rather than the default.

    Alpine, because this is the admin bundle where Alpine is already loaded. The chosen vehicles
    are plain hidden inputs, so the surrounding form posts them like any other field.
--}}
<div x-data="fitmentPicker({{ json_encode([
        'chosen' => collect($chosen)->values()->all(),
        'name' => $name,
    ]) }})" x-init="boot()" class="space-y-4">

    {{--
        A marker that only exists if Alpine ran, and the reason it is inside a <template>.

        The list below is rendered by Alpine. If the admin bundle ever fails to load, those
        hidden inputs are simply absent — and the server would read "no vehicles chosen" and
        wipe every fitment on the product. A blank screen losing data silently is the worst
        shape of bug there is.

        Static HTML inside x-data would still render with Alpine dead. The contents of a
        <template> do not, so this posts if and only if the control was actually working, and
        the controller leaves fitment alone without it.
    --}}
    <template x-if="true">
        <input type="hidden" name="fitment_managed" value="1" />
    </template>

    {{-- The whole list, every time. That is what makes removing a row actually remove it. --}}
    <template x-for="vehicle in chosen" :key="vehicle.id">
        <input type="hidden" :name="fieldName" :value="vehicle.id" />
    </template>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <x-admin.field label="Year" name="fitment-year" hint="Optional — narrows the lists below.">
            <select x-model="year" x-on:change="onYear()"
                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Any year</option>
                <template x-for="y in years" :key="y">
                    <option :value="y" x-text="y"></option>
                </template>
            </select>
        </x-admin.field>

        <x-admin.field label="Make" name="fitment-make">
            <select x-model="make" x-on:change="onMake()"
                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Choose…</option>
                <template x-for="m in makes" :key="m.id">
                    <option :value="m.id" x-text="m.name"></option>
                </template>
            </select>
        </x-admin.field>

        <x-admin.field label="Model" name="fitment-model">
            <select x-model="model" x-on:change="onModel()" :disabled="!models.length"
                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-none disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Choose…</option>
                <template x-for="m in models" :key="m.id">
                    <option :value="m.id" x-text="m.name"></option>
                </template>
            </select>
        </x-admin.field>

        <x-admin.field label="Sub model" name="fitment-sub-model">
            <select x-model="subModel" x-on:change="onSubModel()" :disabled="!subModels.length"
                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-none disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Choose…</option>
                <template x-for="s in subModels" :key="s">
                    <option :value="s" x-text="s"></option>
                </template>
            </select>
        </x-admin.field>

        <x-admin.field label="Engine" name="fitment-engine">
            <select x-model="engine" :disabled="!engines.length"
                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-none disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">All engines</option>
                <template x-for="e in engines" :key="e.id">
                    <option :value="e.id" x-text="e.label"></option>
                </template>
            </select>
        </x-admin.field>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        {{-- type="button" matters: a bare <button> inside a form submits it, so this would
             save the product every time somebody added a vehicle. --}}
        <button type="button" x-on:click="add()" :disabled="!engines.length"
            class="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-40">
            <span x-text="addLabel()"></span>
        </button>

        <p x-show="engines.length" class="text-xs text-gray-500 dark:text-gray-400">
            Leave Engine on “All engines” to add every engine of that sub model at once.
        </p>
    </div>

    <p x-show="error" x-text="error" class="text-xs text-error-600 dark:text-error-400"></p>

    <div class="border-t border-gray-100 pt-4 dark:border-gray-800">
        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
            <span class="font-medium text-gray-700 dark:text-gray-300"
                x-text="chosen.length + (chosen.length === 1 ? ' vehicle' : ' vehicles')"></span>
            <span x-show="!chosen.length">— this part will not appear when a shopper filters by their car.</span>
        </p>

        <div class="flex flex-wrap gap-2">
            <template x-for="vehicle in chosen" :key="vehicle.id">
                <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1.5 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    <span x-text="vehicle.label"></span>
                    <button type="button" x-on:click="remove(vehicle.id)"
                        class="font-medium text-error-600 hover:underline dark:text-error-400"
                        :aria-label="'Remove ' + vehicle.label">&times;</button>
                </span>
            </template>
        </div>
    </div>
</div>
