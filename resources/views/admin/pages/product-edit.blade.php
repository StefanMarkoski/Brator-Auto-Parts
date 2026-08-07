@extends('admin.layouts.admin')
@section('title', 'Edit '.$product->name)
@section('heading', 'Edit product')

@section('content')
    <x-admin.page-breadcrumb :pageTitle="$product->name" />

    @if (session('status'))
        <x-admin.alert variant="success" class="mb-6">{{ session('status') }}</x-admin.alert>
    @endif

    @if ($errors->any())
        <x-admin.alert variant="error" title="Nothing was saved" class="mb-6">
            Check the fields marked below.
        </x-admin.alert>
    @endif

    @if ($product->trashed())
        <x-admin.alert variant="warning" title="This product is deleted" class="mb-6">
            It is hidden from the shop. Past receipts still show it. Restore it to bring it back.
        </x-admin.alert>
    @endif

    {{--
        The edit form wraps the left column ONLY, and the buttons on the right reach it by
        id. It is tempting to wrap the whole grid instead — but the delete control and the
        override-release controls are forms of their own, and a nested <form> is invalid
        HTML that the browser drops silently, so those buttons would do nothing.
    --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <form method="post" id="product-edit" action="{{ route('admin.products.update', $product->id, false) }}">
                @csrf
                @method('PUT')

                <x-admin.component-card title="Details"
                    desc="Editing a value claims that field for you — imports will stop overwriting it.">
                    @include('admin.partials.product-form')
                </x-admin.component-card>

                {{--
                    Inside the same form, so the chosen vehicles post with Save changes and there
                    is one button to press rather than two ways to half-save a product.

                    Unlike every other field on this screen, this list is SYNCED: what you see is
                    what the part will fit. Removing a chip is the whole point of the control, so
                    it has to actually remove — including fitment that arrived from a feed.
                --}}
                {{-- id, so the products list and the Vehicles screen can link straight to it: the
                     card sits under a long Details form and was being missed entirely. --}}
                <x-admin.component-card id="fitment" class="mt-6" title="Fitment"
                    :desc="'Which cars this part fits — '.$fitment->count().' recorded. Removing one here removes it, including fitment that came from a feed.'">
                    <x-admin.fitment-picker :chosen="$fitment" />
                </x-admin.component-card>
            </form>
        </div>

        <div class="space-y-6">
            <x-admin.component-card title="Save">
                <div class="flex flex-col gap-3">
                    <x-admin.button type="submit" form="product-edit">Save changes</x-admin.button>
                    <x-admin.button variant="outline" target="_blank"
                        :href="route('shop.product', $product->slug, false)">View in shop</x-admin.button>
                </div>
            </x-admin.component-card>

            <x-admin.component-card title="Images"
                desc="The first image is what the shop shows on cards and listings. Up to four appear on the product page.">
                {{-- Its own form, and it needs the multipart encoding — a file input inside
                     the details form would post as urlencoded and arrive empty. --}}
                <form method="post" enctype="multipart/form-data"
                    action="{{ route('admin.products.images.store', $product->id, false) }}" class="space-y-3">
                    @csrf
                    <x-admin.field label="Add images" name="images"
                        hint="JPG, PNG or WebP, up to 4MB each. Eight at a time.">
                        <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-brand-500 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white dark:border-gray-700 dark:text-gray-400" />
                    </x-admin.field>

                    <x-admin.button type="submit" variant="outline" size="sm">Upload</x-admin.button>
                </form>

                @if ($images->isNotEmpty())
                    <ul class="space-y-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                        @foreach ($images as $image)
                            <li class="flex items-center gap-3">
                                {{-- Origin-relative, single leading slash: seeded rows hold a
                                     theme asset path and uploads hold storage/…, and both are
                                     relative to the document root. --}}
                                <img src="/{{ $image->path }}" alt="{{ $image->alt }}"
                                    class="h-14 w-14 shrink-0 rounded-lg object-cover" />

                                <div class="min-w-0 flex-1">
                                    @if ($image->is_primary)
                                        <x-admin.badge color="success" size="sm">Main image</x-admin.badge>
                                    @else
                                        <form method="post"
                                            action="{{ route('admin.products.images.update', [$product->id, $image->id], false) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="action" value="primary" />
                                            <button type="submit"
                                                class="text-xs font-medium text-brand-500 hover:underline">Make main</button>
                                        </form>
                                    @endif

                                    <span class="mt-1 block truncate text-xs text-gray-400">{{ $image->path }}</span>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    @foreach (['up' => '↑', 'down' => '↓'] as $direction => $glyph)
                                        <form method="post"
                                            action="{{ route('admin.products.images.update', [$product->id, $image->id], false) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="action" value="{{ $direction }}" />
                                            <button type="submit"
                                                @disabled($direction === 'up' ? $loop->parent->first : $loop->parent->last)
                                                class="px-1 text-sm text-gray-400 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:text-white">{{ $glyph }}</button>
                                        </form>
                                    @endforeach

                                    <x-admin.confirm-action
                                        :action="route('admin.products.images.destroy', [$product->id, $image->id], false)"
                                        method="DELETE"
                                        label="Remove"
                                        trigger-class="text-xs font-medium text-error-600 hover:underline dark:text-error-400"
                                        title="Remove this image?"
                                        :message="str_starts_with($image->path, 'storage/')
                                            ? 'The file is deleted from disk as well. This cannot be undone.'
                                            : 'This is one of the theme\'s own images, shared with other products, so only the link to it is removed — the file stays.'"
                                        confirm="Yes, remove it" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="border-t border-gray-100 pt-4 text-sm text-gray-400 dark:border-gray-800">
                        No images yet, so the shop shows a placeholder for this product.
                    </p>
                @endif
            </x-admin.component-card>

            <x-admin.component-card title="Fields you own"
                desc="Imports skip these. Release one to let the supplier feed update it again.">
                @forelse ($overridden as $field)
                    <form method="post" action="{{ route('admin.products.override.release', $product->id, false) }}"
                        class="flex items-center justify-between gap-3 text-sm">
                        @csrf
                        @method('DELETE')
                        <code class="text-gray-700 dark:text-gray-300">{{ $field }}</code>
                        <input type="hidden" name="field" value="{{ $field }}" />
                        <button type="submit"
                            class="text-xs text-error-600 hover:underline dark:text-error-400">Release</button>
                    </form>
                @empty
                    <p class="text-sm text-gray-400">Nothing yet — every field here is still owned by the supplier feed.</p>
                @endforelse
            </x-admin.component-card>

            <x-admin.component-card title="Danger zone"
                desc="Deleting hides the product from the shop. Receipts keep their own copy of the name, SKU and price, so order history is unaffected.">
                @if ($product->trashed())
                    <form method="post" action="{{ route('admin.products.restore', $product->id, false) }}">
                        @csrf
                        <x-admin.button type="submit" variant="outline" size="sm">Restore product</x-admin.button>
                    </form>
                @else
                    <x-admin.confirm-action
                        :action="route('admin.products.destroy', $product->id, false)"
                        method="DELETE"
                        label="Delete product"
                        :title="'Delete '.$product->name.'?'"
                        message="It will disappear from the shop immediately. Past receipts are unaffected, and you can restore it from the deleted list."
                        confirm="Yes, delete it" />
                @endif
            </x-admin.component-card>
        </div>
    </div>
@endsection
