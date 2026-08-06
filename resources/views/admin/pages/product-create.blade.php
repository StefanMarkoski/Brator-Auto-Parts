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
                    desc="Which cars this part fits comes from the import feed's fits column, not from here — a part fits hundreds of engine variants and picking them by hand is not the job.">
                    <p class="text-sm text-gray-400">
                        A product with no fitment records still sells; it just will not appear when
                        a shopper filters by their car. To give this part fitment, put it in a feed
                        with a <code>fits</code> column — an existing SKU is updated rather than
                        duplicated, so a one-row file is enough.
                    </p>
                    <x-admin.button variant="outline" size="sm"
                        :href="route('admin.imports.index', [], false)">Go to imports</x-admin.button>
                </x-admin.component-card>
            </div>
        </div>
    </form>
@endsection
