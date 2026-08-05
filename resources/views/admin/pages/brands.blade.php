@extends('admin.layouts.admin')
@section('title', 'Brands')
@section('heading', 'Brands')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Brands" />
    <x-admin.component-card title="Brands" :desc="number_format($brands->total()).' manufacturers'">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-400">
                    <tr><th class="pb-3 pr-4">Brand</th><th class="pb-3 pr-4">Slug</th>
                        <th class="pb-3 pr-4 text-right">Products</th><th class="pb-3 pr-4">Active</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($brands as $brand)
                        <tr>
                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">{{ $brand->name }}</td>
                            <td class="py-3 pr-4 text-gray-400"><code>{{ $brand->slug }}</code></td>
                            <td class="py-3 pr-4 text-right">{{ number_format($brand->products_count) }}</td>
                            <td class="py-3 pr-4">{{ $brand->is_active ? 'Yes' : 'No' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $brands->links() }}</div>
    </x-admin.component-card>
@endsection
