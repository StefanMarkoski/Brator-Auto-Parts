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
