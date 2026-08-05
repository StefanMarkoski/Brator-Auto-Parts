@extends('admin.layouts.admin')
@section('title', 'Products')
@section('heading', 'Products')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Products" />

    <x-admin.component-card title="Catalogue" :desc="number_format($products->total()).' products'">
        <form method="get" class="mb-5 flex flex-wrap gap-3">
            <input type="search" name="q" value="{{ $search }}" placeholder="Name or SKU"
                class="h-11 flex-1 min-w-64 rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:text-white/90" />
            <select name="stock" x-on:change="$el.form.requestSubmit()"
                class="h-11 rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:text-white/90">
                <option value="">All stock</option>
                <option value="low" @selected(request('stock') === 'low')>Low (≤5)</option>
                <option value="out" @selected(request('stock') === 'out')>Out of stock</option>
            </select>
            <button type="submit" class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600">Search</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-400">
                    <tr>
                        <th class="pb-3 pr-4">Product</th>
                        <th class="pb-3 pr-4">Brand</th>
                        <th class="pb-3 pr-4 text-right">Price (net)</th>
                        <th class="pb-3 pr-4 text-right">Stock</th>
                        <th class="pb-3 pr-4">Status</th>
                        <th class="pb-3 pr-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($products as $product)
                        <tr>
                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">{{ $product->name }}
                                <span class="block text-xs text-gray-400">{{ $product->sku }}</span></td>
                            <td class="py-3 pr-4 text-gray-500">{{ $product->brand?->name ?? '—' }}</td>
                            <td class="py-3 pr-4 text-right">{{ $product->price_minor->format() }}
                                @if ($product->sale_price_minor)
                                    <span class="block text-xs text-success-600">on sale {{ $product->sale_price_minor->format() }}</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-right {{ $product->stock_quantity <= 5 ? 'text-warning-600 font-medium' : 'text-gray-500' }}">{{ $product->stock_quantity }}</td>
                            <td class="py-3 pr-4">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $product->stock_status->isBuyable() ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400' : 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400' }}">
                                    {{ $product->stock_status->label() }}
                                </span>
                            </td>
                            <td class="py-3 pr-4 text-right">
                                <a href="{{ route('admin.products.edit', $product->id, false) }}"
                                    class="text-brand-500 hover:underline">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-400">No products match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $products->links() }}</div>
    </x-admin.component-card>
@endsection
