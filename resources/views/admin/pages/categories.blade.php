@extends('admin.layouts.admin')
@section('title', 'Categories')
@section('heading', 'Categories')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Categories" />
    <x-admin.component-card title="Category tree" desc="Sub-categories drive the listing filters — each carries its own filter set.">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($categories as $parent)
                <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                    <a href="{{ route('shop.category', $parent->slug, false) }}" target="_blank"
                        class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{{ $parent->name }}</a>
                    <span class="ml-1 text-xs text-gray-400">({{ number_format($parent->products_count) }})</span>
                    <ul class="mt-3 space-y-1.5 text-sm">
                        @foreach ($parent->children as $child)
                            <li class="flex justify-between gap-2">
                                <a href="{{ route('shop.category', $child->slug, false) }}" target="_blank"
                                    class="text-gray-600 hover:text-brand-500 dark:text-gray-400">{{ $child->name }}</a>
                                <span class="text-xs text-gray-400">{{ number_format($child->products_count) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </x-admin.component-card>
@endsection
