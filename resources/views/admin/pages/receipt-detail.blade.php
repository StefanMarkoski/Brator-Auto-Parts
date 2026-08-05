@extends('admin.layouts.admin')
@section('title', 'Receipt '.$receipt->receipt_number)
@section('heading', 'Receipt '.$receipt->receipt_number)

@section('content')
    <x-admin.page-breadcrumb :pageTitle="'Receipt '.$receipt->receipt_number" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-admin.component-card title="Lines" desc="Values recorded at the time of purchase — they do not change when a product does.">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-400">
                            <tr>
                                <th class="pb-3 pr-4">Part</th>
                                <th class="pb-3 pr-4">SKU</th>
                                <th class="pb-3 pr-4 text-right">Unit (net)</th>
                                <th class="pb-3 pr-4 text-right">Qty</th>
                                <th class="pb-3 pr-4 text-right">VAT</th>
                                <th class="pb-3 pr-4 text-right">Line</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($receipt->lines as $line)
                                <tr>
                                    <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">{{ $line->product_name_snapshot }}
                                        <span class="block text-xs text-gray-400">{{ $line->brand_name_snapshot }}</span></td>
                                    <td class="py-3 pr-4 text-gray-500">{{ $line->product_sku_snapshot }}</td>
                                    <td class="py-3 pr-4 text-right">{{ $line->unit_price_minor->format() }}</td>
                                    <td class="py-3 pr-4 text-right">{{ $line->quantity }}</td>
                                    <td class="py-3 pr-4 text-right text-gray-500">{{ $line->vat_minor->format() }}
                                        <span class="block text-xs text-gray-400">@ {{ rtrim(rtrim(number_format($line->vat_rate, 2), '0'), '.') }}%</span></td>
                                    <td class="py-3 pr-4 text-right font-medium">{{ $line->line_total_minor->format() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <dl class="ml-auto max-w-xs space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Subtotal (net)</dt><dd>{{ $receipt->subtotal_minor->format() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">VAT</dt><dd>{{ $receipt->vat_minor->format() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Delivery</dt><dd>{{ $receipt->shipping_minor->isZero() ? 'Free' : $receipt->shipping_minor->format() }}</dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-2 font-semibold dark:border-gray-800">
                        <dt>Total</dt><dd>{{ $receipt->total_minor->format() }}</dd></div>
                </dl>
            </x-admin.component-card>
        </div>

        <x-admin.component-card title="Customer">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-gray-500 dark:text-gray-400">Name</dt><dd class="text-gray-800 dark:text-white/90">{{ $receipt->customer_name }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd><a href="mailto:{{ $receipt->customer_email }}" class="text-brand-500 hover:underline">{{ $receipt->customer_email }}</a></dd></div>
                @if ($receipt->customer_phone)
                    <div><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $receipt->customer_phone) }}" class="text-brand-500 hover:underline">{{ $receipt->customer_phone }}</a></dd></div>
                @endif
                <div><dt class="text-gray-500 dark:text-gray-400">Delivery address</dt>
                    <dd class="whitespace-pre-line text-gray-800 dark:text-white/90">{{ $receipt->shipping_address }}</dd></div>
                @if ($receipt->notes)
                    <div><dt class="text-gray-500 dark:text-gray-400">Customer notes</dt>
                        <dd class="whitespace-pre-line text-gray-800 dark:text-white/90">{{ $receipt->notes }}</dd></div>
                @endif
            </dl>
        </x-admin.component-card>
    </div>
@endsection
