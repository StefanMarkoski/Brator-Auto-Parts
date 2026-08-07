@extends('admin.layouts.admin')
@section('title', 'Products')
@section('heading', 'Products')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Products" />

    @if (session('status'))
        <x-admin.alert variant="success" class="mb-6">{{ session('status') }}</x-admin.alert>
    @endif

    {{--
        Arrived from a vehicle. Says which car, and how to get back out — a filtered list with no
        statement of what it is filtered by is how staff conclude the catalogue has shrunk.
    --}}
    @if ($fitsVehicle !== null)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/30 dark:bg-brand-500/10">
            <p class="text-sm text-gray-700 dark:text-gray-300">
                Parts that fit <span class="font-medium">{{ $fitsVehicle['label'] }}</span>
                <span class="text-gray-400">({{ $fitsVehicle['years'] }})</span>.
                Open a part to add or remove this car — fitment is set per part.
            </p>

            <a href="{{ route('admin.products.index', request()->except(['fits', 'page']), false) }}"
                class="text-sm font-medium text-brand-500 hover:underline">Show all parts</a>
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <x-admin.button size="sm" :variant="$showDeleted ? 'outline' : 'primary'"
                :href="route('admin.products.index', request()->except(['status', 'page']), false)">
                Active
            </x-admin.button>

            {{-- Only offered when there is something to see, so it is never a dead end. --}}
            @if ($deletedCount > 0)
                <x-admin.button size="sm" :variant="$showDeleted ? 'primary' : 'outline'"
                    :href="route('admin.products.index', ['status' => 'deleted'] + request()->except('page'), false)">
                    Deleted ({{ number_format($deletedCount) }})
                </x-admin.button>
            @endif
        </div>

        <x-admin.button size="sm" :href="route('admin.products.create', [], false)">+ New product</x-admin.button>
    </div>

    <x-admin.component-card title="Catalogue" :desc="number_format($products->total()).($showDeleted ? ' deleted products' : ' products')">
        <form method="get" class="mb-5 flex flex-wrap gap-3">
            {{-- Carried through the search, or searching would silently drop you back to
                 the active list. --}}
            @if ($showDeleted)
                <input type="hidden" name="status" value="deleted" />
            @endif

            <x-admin.input type="search" name="q" :value="$search" placeholder="Name or SKU" class="flex-1 min-w-64" />

            {{-- Alpine, not the storefront's data-auto-submit: storefront.js is not loaded
                 on admin pages, and the admin's selects are plain elements with no select2
                 wrapper, so a native change is all this needs. --}}
            <x-admin.select name="stock" x-on:change="$el.form.requestSubmit()"
                :options="['low' => 'Low (≤5)', 'out' => 'Out of stock']"
                :selected="request('stock')" placeholder="All stock" class="w-44" />

            <x-admin.button type="submit" size="sm">Search</x-admin.button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-400">
                    <tr>
                        <th class="pb-3 pr-4">Product</th>
                        <th class="pb-3 pr-4">Brand</th>
                        <th class="pb-3 pr-4 text-right">Price (net)</th>
                        <th class="pb-3 pr-4 text-right">Stock</th>
                        <th class="pb-3 pr-4 text-right">Fits</th>
                        <th class="pb-3 pr-4">Status</th>
                        <th class="pb-3 pr-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($products as $product)
                        <tr>
                            <td class="py-3 pr-4">
                                <a href="{{ route('admin.products.edit', $product->id, false) }}"
                                    class="font-medium text-gray-700 hover:text-brand-500 dark:text-gray-300">{{ $product->name }}</a>
                                <span class="block text-xs text-gray-400">{{ $product->sku }}</span>
                            </td>
                            <td class="py-3 pr-4 text-gray-500">{{ $product->brand?->name ?? '—' }}</td>
                            <td class="py-3 pr-4 text-right text-gray-700 dark:text-gray-300">
                                {{ $product->price_minor->format() }}
                                @if ($product->sale_price_minor)
                                    <span class="block text-xs text-success-600">on sale {{ $product->sale_price_minor->format() }}</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-right {{ $product->stock_quantity <= 5 ? 'font-medium text-warning-600' : 'text-gray-500' }}">
                                {{ $product->stock_quantity }}
                            </td>
                            {{--
                                How many cars this part fits, linking straight to the Fitment
                                card on its own screen. Fitment was editable there all along and
                                nothing on this list said so, which is a control nobody can find.
                                A part fitting nothing is called out, because that part is
                                invisible to every shopper who filters by their vehicle.
                            --}}
                            <td class="py-3 pr-4 text-right">
                                <a href="{{ route('admin.products.edit', $product->id, false) }}#fitment"
                                    class="{{ $product->vehicle_variants_count === 0
                                        ? 'font-medium text-warning-600 hover:underline'
                                        : 'text-gray-500 hover:text-brand-500 hover:underline' }}">
                                    {{ $product->vehicle_variants_count === 0
                                        ? 'No cars'
                                        : number_format($product->vehicle_variants_count).' cars' }}
                                </a>
                            </td>
                            <td class="py-3 pr-4">
                                @if ($product->trashed())
                                    <x-admin.badge color="light" size="sm">Deleted</x-admin.badge>
                                @elseif ($product->published_at === null)
                                    <x-admin.badge color="warning" size="sm">Unpublished</x-admin.badge>
                                @elseif (! $product->is_active)
                                    <x-admin.badge color="light" size="sm">Hidden</x-admin.badge>
                                @else
                                    <x-admin.badge :color="$product->stock_status->isBuyable() ? 'success' : 'error'" size="sm">
                                        {{ $product->stock_status->label() }}
                                    </x-admin.badge>
                                @endif
                            </td>
                            {{--
                                Inline controls rather than a three-dot dropdown, and that is
                                a considered choice. The modal has to be a sibling of its
                                trigger; rendered inside a dropdown panel it is governed by
                                the panel's x-show, so closing the menu takes the open modal
                                with it — and the table's overflow-x-auto would clip the
                                panel anyway. Two visible controls also spare a click.
                            --}}
                            <td class="py-3 pr-4">
                                <div class="flex items-center justify-end gap-4">
                                    @if ($product->trashed())
                                        <form method="post"
                                            action="{{ route('admin.products.restore', $product->id, false) }}">
                                            @csrf
                                            <button type="submit"
                                                class="text-sm font-medium text-brand-500 hover:underline">Restore</button>
                                        </form>
                                    @else
                                        <a href="{{ route('admin.products.edit', $product->id, false) }}"
                                            class="text-sm font-medium text-brand-500 hover:underline">Edit</a>

                                        <x-admin.confirm-action
                                            :action="route('admin.products.destroy', $product->id, false)"
                                            method="DELETE"
                                            label="Delete"
                                            trigger-class="text-sm font-medium text-error-600 hover:underline dark:text-error-400"
                                            :title="'Delete '.$product->name.'?'"
                                            message="It will disappear from the shop immediately. Past receipts are unaffected, and you can restore it from the deleted list."
                                            confirm="Yes, delete it" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-gray-400">
                                {{ $showDeleted ? 'Nothing has been deleted.' : 'No products match.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $products->links() }}</div>
    </x-admin.component-card>
@endsection
