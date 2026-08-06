{{--
    The product form, shared by create and edit.

    One copy on purpose. Two forms describing the same row is how a field ends up editable
    on one screen and not the other — which is exactly the state this panel was in, where
    stock_quantity and published_at could not be set anywhere at all.

    Expects: $product (null when creating), $brands, $categories, $stockStatuses,
    $conditions, $overridden, $selectedCategories.
--}}
@php
    $editing = $product !== null;
    $owned = fn (string $field) => in_array($field, $overridden ?? [], true);

    $money = fn (?object $value) => $value === null ? '' : number_format($value->toMajor(), 2, '.', '');
@endphp

<div class="space-y-5">
    <x-admin.field label="Name" name="name" :required="true" :owned="$owned('name')">
        <x-admin.input name="name" :value="old('name', $product?->name)" required autofocus />
    </x-admin.field>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-admin.field label="SKU" name="sku" :required="true"
            :hint="$editing ? 'The supplier part number. Not editable once a product exists — receipts and imports match on it.' : 'The supplier part number. Must be unique.'">
            @if ($editing)
                {{-- Shown, not editable: changing it would orphan every cross-reference and
                     import row that matches on this value. --}}
                <x-admin.input :value="$product->sku" disabled class="opacity-60" />
            @else
                <x-admin.input name="sku" :value="old('sku')" required />
            @endif
        </x-admin.field>

        <x-admin.field label="URL slug" name="slug"
            :hint="$editing ? 'Changing this breaks any link already shared.' : 'Leave blank to build it from the name.'">
            <x-admin.input name="slug" :value="old('slug', $product?->slug)"
                placeholder="brake-disc-front-vented" />
        </x-admin.field>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-admin.field label="Price, net of VAT (ден)" name="price_major" :required="true"
            :owned="$owned('price_minor')"
            :hint="'VAT of '.(int) config('shop.vat_rate').'% is added at checkout, not stored here.'">
            <x-admin.input type="number" step="0.01" min="0" name="price_major"
                :value="old('price_major', $editing ? $money($product->price_minor) : '')" required />
        </x-admin.field>

        <x-admin.field label="Sale price (optional)" name="sale_price_major"
            hint="Shown instead of the price, and used for sorting and filtering.">
            <x-admin.input type="number" step="0.01" min="0" name="sale_price_major"
                :value="old('sale_price_major', $editing ? $money($product->sale_price_minor) : '')" />
        </x-admin.field>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <x-admin.field label="Brand" name="brand_id">
            <x-admin.select name="brand_id" placeholder="—"
                :options="$brands->pluck('name', 'id')->all()"
                :selected="$product?->brand_id" />
        </x-admin.field>

        <x-admin.field label="Stock on hand" name="stock_quantity" :required="true"
            hint="The counted figure. The change is written to the stock ledger.">
            <x-admin.input type="number" min="0" step="1" name="stock_quantity"
                :value="old('stock_quantity', $editing ? $product->stock_quantity : 0)" required />
        </x-admin.field>

        <x-admin.field label="Stock status" name="stock_status">
            <x-admin.select name="stock_status"
                :options="collect($stockStatuses)->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()"
                :selected="$product?->stock_status->value" />
        </x-admin.field>
    </div>

    <x-admin.field label="Condition" name="condition">
        <x-admin.select name="condition"
            :options="collect($conditions)->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()"
            :selected="$product?->condition->value" />
    </x-admin.field>

    <x-admin.field label="Categories" name="category_ids"
        hint="Sub-categories only — these are what shoppers filter by.">
        <div class="max-h-56 space-y-1.5 overflow-y-auto rounded-lg border border-gray-300 p-3 dark:border-gray-700">
            @php($chosen = old('category_ids', $selectedCategories ?? []))
            @foreach ($categories as $category)
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400">
                    <input type="checkbox" name="category_ids[]" value="{{ $category->id }}"
                        @checked(in_array($category->id, $chosen, true))
                        class="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700" />
                    <span>
                        @if ($category->parent !== null)
                            <span class="text-gray-400">{{ $category->parent->name }} ›</span>
                        @endif
                        {{ $category->name }}
                    </span>
                </label>
            @endforeach
        </div>
    </x-admin.field>

    <x-admin.field label="Short description" name="short_description">
        <x-admin.textarea name="short_description" rows="3">{{ old('short_description', $product?->short_description) }}</x-admin.textarea>
    </x-admin.field>

    <div class="flex flex-wrap gap-8 pt-2">
        <x-admin.toggle name="is_active" label="Visible in the shop"
            :checked="$editing ? $product->is_active : true" />

        {{-- published_at is the field that actually gates visibility, so it needs its own
             control rather than being implied by is_active. --}}
        <x-admin.toggle name="published" label="Published"
            :checked="$editing ? $product->published_at !== null : true" />
    </div>

    @if ($editing && $product->published_at !== null)
        {{-- Preserved so re-saving does not restamp the date and reshuffle "new arrivals". --}}
        <input type="hidden" name="published_at_existing" value="{{ $product->published_at->toDateTimeString() }}" />
    @endif
</div>
