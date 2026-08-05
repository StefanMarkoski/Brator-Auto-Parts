@extends('admin.layouts.admin')
@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Dashboard" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Revenue (paid)', 'value' => $revenue->format(), 'note' => 'incl. VAT'],
            ['label' => 'VAT collected', 'value' => $vatCollected->format(), 'note' => (int) config('shop.vat_rate').'% on net'],
            ['label' => 'Receipts', 'value' => number_format($receiptsTotal), 'note' => number_format($receiptsThisMonth).' this month'],
            ['label' => 'Active products', 'value' => number_format($productsActive), 'note' => number_format($productsOutOfStock).' out of stock'],
        ] as $tile)
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tile['label'] }}</p>
                <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $tile['value'] }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ $tile['note'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-admin.component-card title="Latest receipts" desc="Newest orders first.">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-400">
                            <tr>
                                <th class="pb-3 pr-4">Receipt</th>
                                <th class="pb-3 pr-4">Customer</th>
                                <th class="pb-3 pr-4">Items</th>
                                <th class="pb-3 pr-4 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($latestReceipts as $receipt)
                                <tr>
                                    <td class="py-3 pr-4">
                                        <a href="{{ route('admin.receipts.show', $receipt->id, false) }}"
                                            class="font-medium text-brand-500 hover:underline">{{ $receipt->receipt_number }}</a>
                                        <span class="block text-xs text-gray-400">{{ $receipt->placed_at?->format('d M Y H:i') }}</span>
                                    </td>
                                    <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">{{ $receipt->customer_name }}</td>
                                    <td class="py-3 pr-4 text-gray-500">{{ $receipt->lines->count() }}</td>
                                    <td class="py-3 pr-4 text-right font-medium text-gray-800 dark:text-white/90">{{ $receipt->total_minor->format() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-gray-400">No receipts yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.component-card>
        </div>

        <div class="space-y-6">
            <x-admin.component-card title="Running low" desc="Five or fewer in stock.">
                <ul class="space-y-3">
                    @forelse ($lowStock as $item)
                        <li class="flex items-start justify-between gap-3">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item->name }}
                                <span class="block text-xs text-gray-400">{{ $item->sku }}</span></span>
                            <span class="rounded-full bg-warning-50 px-2 py-0.5 text-xs font-medium text-warning-600 dark:bg-warning-500/10 dark:text-warning-400">{{ $item->stock_quantity }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-gray-400">Nothing running low.</li>
                    @endforelse
                </ul>
            </x-admin.component-card>

            <x-admin.component-card title="Catalogue health">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Fitment records</dt>
                        <dd class="font-medium text-gray-800 dark:text-white/90">{{ number_format($fitmentRows) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Fields you own</dt>
                        <dd class="font-medium text-gray-800 dark:text-white/90">{{ number_format($overriddenFields) }}</dd></div>
                </dl>
                <p class="text-xs text-gray-400">"Fields you own" are values edited by hand. Imports will not overwrite them.</p>
            </x-admin.component-card>
        </div>
    </div>
@endsection
