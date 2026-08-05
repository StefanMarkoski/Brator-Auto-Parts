@extends('admin.layouts.admin')
@section('title', 'Edit '.$product->name)
@section('heading', 'Edit product')

@section('content')
    <x-admin.page-breadcrumb :pageTitle="$product->name" />

    @if (session('status'))
        <div class="mb-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-admin.component-card title="Details" desc="Editing a value claims that field for you — imports will stop overwriting it.">
                <form method="post" action="{{ route('admin.products.update', $product->id, false) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name
                            @if (in_array('name', $overridden, true))
                                <span class="ml-1 rounded bg-brand-50 px-1.5 py-0.5 text-xs text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">yours</span>
                            @endif
                        </label>
                        <input type="text" name="name" value="{{ old('name', $product->name) }}" required
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Price, net of VAT (ден)
                                @if (in_array('price_minor', $overridden, true))
                                    <span class="ml-1 rounded bg-brand-50 px-1.5 py-0.5 text-xs text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">yours</span>
                                @endif
                            </label>
                            <input type="number" step="0.01" min="0" name="price_major"
                                value="{{ old('price_major', number_format($product->price_minor->toMajor(), 2, '.', '')) }}" required
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:text-white/90" />
                            <p class="mt-1 text-xs text-gray-400">VAT of {{ (int) config('shop.vat_rate') }}% is added at checkout, not stored here.</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sale price (optional)</label>
                            <input type="number" step="0.01" min="0" name="sale_price_major"
                                value="{{ old('sale_price_major', $product->sale_price_minor ? number_format($product->sale_price_minor->toMajor(), 2, '.', '') : '') }}"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:text-white/90" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Brand</label>
                            <select name="brand_id" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:text-white/90">
                                <option value="">—</option>
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id) === $brand->id)>{{ $brand->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock status</label>
                            <select name="stock_status" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:text-white/90">
                                @foreach ($stockStatuses as $case)
                                    <option value="{{ $case->value }}" @selected(old('stock_status', $product->stock_status->value) === $case->value)>{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Condition</label>
                            <select name="condition" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:text-white/90">
                                @foreach ($conditions as $case)
                                    <option value="{{ $case->value }}" @selected(old('condition', $product->condition->value) === $case->value)>{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Short description</label>
                        <textarea name="short_description" rows="3"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm dark:border-gray-700 dark:text-white/90">{{ old('short_description', $product->short_description) }}</textarea>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active)) class="rounded border-gray-300" />
                        Visible in the shop
                    </label>

                    <div class="flex gap-3">
                        <button type="submit" class="rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600">Save changes</button>
                        <a href="{{ route('shop.product', $product->slug, false) }}" target="_blank"
                            class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">View in shop</a>
                    </div>
                </form>
            </x-admin.component-card>
        </div>

        <x-admin.component-card title="Fields you own" desc="Imports skip these. Release one to let the supplier feed update it again.">
            @forelse ($overridden as $field)
                <div class="flex items-center justify-between gap-3 text-sm">
                    <code class="text-gray-700 dark:text-gray-300">{{ $field }}</code>
                    <form method="post" action="{{ route('admin.products.override.release', $product->id, false) }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="field" value="{{ $field }}" />
                        <button type="submit" class="text-xs text-error-600 hover:underline dark:text-error-400">Release</button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-gray-400">Nothing yet — every field here is still owned by the supplier feed.</p>
            @endforelse
        </x-admin.component-card>
    </div>
@endsection
