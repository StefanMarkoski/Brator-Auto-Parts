@extends('admin.layouts.admin')
@section('title', 'New product')
@section('heading', 'New product')

@section('content')
    <x-admin.page-breadcrumb pageTitle="New product" />

    @if ($errors->any())
        <x-admin.alert variant="error" title="The product was not created" class="mb-6">
            Check the fields marked below.
        </x-admin.alert>
    @endif

    <form method="post" action="{{ route('admin.products.store', [], false) }}">
        @csrf

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <x-admin.component-card title="Details"
                    desc="Everything a shopper sees. Publish it when you are ready for it to appear.">
                    @include('admin.partials.product-form', [
                        'product' => null,
                        'overridden' => [],
                        'selectedCategories' => [],
                    ])
                </x-admin.component-card>
            </div>

            <div class="space-y-6">
                <x-admin.component-card title="Save">
                    <div class="flex flex-col gap-3">
                        <x-admin.button type="submit">Create product</x-admin.button>
                        <x-admin.button variant="outline"
                            :href="route('admin.products.index', [], false)">Cancel</x-admin.button>
                    </div>
                </x-admin.component-card>

                <x-admin.component-card title="Fitment"
                    desc="Which cars this part fits. Narrow down the same way a shopper does, then add — or set it in bulk from a feed's fits column.">
                    {{-- Seeded from what was posted, so a save rejected on some other field does
                         not quietly throw away the vehicles that were chosen here. --}}
                    <x-admin.fitment-picker :chosen="$fitment" />

                    <p class="mt-4 border-t border-gray-100 pt-4 text-xs text-gray-400 dark:border-gray-800">
                        For more than a handful of parts, the <code>fits</code> column in an import
                        feed is the faster route — an existing SKU is updated rather than
                        duplicated, so a one-row file is enough to give a part its vehicles.
                    </p>
                </x-admin.component-card>
            </div>
        </div>
    </form>
@endsection
