@extends('admin.layouts.admin')
@section('title', 'Imports')
@section('heading', 'Imports')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Imports" />

    <div class="mb-6 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
        The import <em>runner</em> is not built yet — this screen shows the sources and run
        history the schema already supports. What is enforced today is the rule underneath
        it: any field edited by hand is recorded, and an import will not overwrite it.
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-admin.component-card title="Sources" desc="Where catalogue data comes from.">
            @forelse ($sources as $source)
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="text-gray-700 dark:text-gray-300">{{ $source->name }}
                        <span class="block text-xs text-gray-400">{{ strtoupper($source->type->value) }} &middot;
                            last run {{ $source->last_run_at?->diffForHumans() ?? 'never' }}</span></span>
                    <span class="rounded-full px-2 py-0.5 text-xs {{ $source->is_active ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06]' }}">
                        {{ $source->is_active ? 'Active' : 'Paused' }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400">No sources configured yet.</p>
            @endforelse
        </x-admin.component-card>

        <x-admin.component-card title="Recent runs">
            @forelse ($runs as $run)
                <div class="text-sm">
                    <span class="text-gray-700 dark:text-gray-300">{{ $run->source?->name ?? 'Unknown source' }}</span>
                    <span class="block text-xs text-gray-400">
                        {{ $run->status->value }} &middot; {{ number_format($run->rows_created) }} created,
                        {{ number_format($run->rows_updated) }} updated, {{ number_format($run->rows_skipped) }} skipped
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-400">No import runs recorded.</p>
            @endforelse
        </x-admin.component-card>
    </div>
@endsection
