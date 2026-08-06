@extends('admin.layouts.admin')
@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    @use('App\Domain\Ordering\Enums\ReceiptStatus')

    <x-admin.page-breadcrumb pageTitle="Dashboard" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 md:gap-6">
        {{-- The note says what the pill is measured against, because "down 12%" with no
             stated comparison is a number you cannot act on. --}}
        <x-admin.metric-card icon="box" label="Revenue this month" :value="$revenueThisMonth->format()"
            :note="$revenue->format().' all time · vs same days last month'" :trend="$revenueTrend" />

        <x-admin.metric-card icon="box" label="Orders this month" :value="number_format($receiptsThisMonth)"
            :note="number_format($receiptsTotal).' paid all time · vs same days last month'" :trend="$receiptsTrend" />

        <x-admin.metric-card icon="box" label="VAT collected" :value="$vatCollected->format()"
            :note="(int) config('shop.vat_rate').'% on net, all time'" />

        {{-- No trend on this one, so no pill: there is no previous-period figure for
             out-of-stock, and inventing one to fill the space is how a dashboard starts
             lying. rise-is-good is set anyway so that a rise reads red if a trend is ever
             wired in — more unbuyable parts is not good news. --}}
        <x-admin.metric-card icon="people" label="Out of stock" :value="number_format($productsOutOfStock)"
            :note="number_format($productsActive).' active products'" :rise-is-good="false" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-admin.component-card title="Revenue by month" desc="Paid orders only, incl. VAT, last twelve months.">
                {{-- ApexCharts is loaded on demand by window.adminChart, so the other admin
                     pages do not download 900kB for a chart they never draw. --}}
                <div x-data x-init="
                    window.adminChart($refs.chart, {
                        series: [{ name: 'Revenue', data: @js($revenueSeries['values']) }],
                        colors: ['#465fff'],
                        chart: { fontFamily: 'Outfit, sans-serif', type: 'bar', height: 220, toolbar: { show: false } },
                        plotOptions: { bar: { horizontal: false, columnWidth: '39%', borderRadius: 5, borderRadiusApplication: 'end' } },
                        dataLabels: { enabled: false },
                        stroke: { show: true, width: 4, colors: ['transparent'] },
                        xaxis: { categories: @js($revenueSeries['labels']), axisBorder: { show: false }, axisTicks: { show: false } },
                        legend: { show: false },
                        yaxis: { title: false, labels: { formatter: (v) => new Intl.NumberFormat('mk-MK', { notation: 'compact' }).format(v) } },
                        grid: { yaxis: { lines: { show: true } } },
                        fill: { opacity: 1 },
                        tooltip: {
                            x: { show: false },
                            y: { formatter: (v) => new Intl.NumberFormat('mk-MK', { minimumFractionDigits: 2 }).format(v) + ' ден' },
                        },
                    })
                ">
                    <div x-ref="chart"></div>
                </div>
            </x-admin.component-card>
        </div>

        <x-admin.component-card title="Needs attention"
            desc="The three states that quietly cost money if nobody looks.">
            <dl class="space-y-4 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Unpublished products
                        <span class="block text-xs text-gray-400">Not visible to shoppers at all.</span></dt>
                    <dd>
                        @if ($unpublishedCount > 0)
                            <a href="{{ route('admin.products.index', [], false) }}"
                                class="font-medium text-brand-500 hover:underline">{{ number_format($unpublishedCount) }}</a>
                        @else
                            <span class="font-medium text-gray-800 dark:text-white/90">0</span>
                        @endif
                    </dd>
                </div>

                <div class="flex items-start justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Out of stock
                        <span class="block text-xs text-gray-400">Listed but unbuyable.</span></dt>
                    <dd>
                        @if ($productsOutOfStock > 0)
                            <a href="{{ route('admin.products.index', ['stock' => 'out'], false) }}"
                                class="font-medium text-brand-500 hover:underline">{{ number_format($productsOutOfStock) }}</a>
                        @else
                            <span class="font-medium text-gray-800 dark:text-white/90">0</span>
                        @endif
                    </dd>
                </div>

                <div class="flex items-start justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Cancelled orders
                        <span class="block text-xs text-gray-400">Stock was returned to the shelf.</span></dt>
                    <dd>
                        @if ($cancelledCount > 0)
                            <a href="{{ route('admin.receipts.index', ['status' => 'cancelled'], false) }}"
                                class="font-medium text-brand-500 hover:underline">{{ number_format($cancelledCount) }}</a>
                        @else
                            <span class="font-medium text-gray-800 dark:text-white/90">0</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-admin.component-card>
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
                                <th class="pb-3 pr-4">Status</th>
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
                                    {{-- withCount, not a lines relation loaded per row: eight
                                         rows meant nine queries and a full line set in memory
                                         to print one integer. --}}
                                    <td class="py-3 pr-4 text-gray-500">{{ $receipt->lines_count }}</td>
                                    <td class="py-3 pr-4">
                                        <x-admin.badge size="sm" :color="match ($receipt->status) {
                                            ReceiptStatus::Paid => 'success',
                                            ReceiptStatus::Cancelled => 'error',
                                            default => 'warning',
                                        }">{{ $receipt->status->label() }}</x-admin.badge>
                                    </td>
                                    <td class="py-3 pr-4 text-right font-medium text-gray-800 dark:text-white/90">{{ $receipt->total_minor->format() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-gray-400">No receipts yet.</td></tr>
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
                            {{-- Linked to its editor, so the count is a starting point rather
                                 than a fact you then have to go and search for. --}}
                            <a href="{{ route('admin.products.edit', $item->id, false) }}"
                                class="text-sm text-gray-700 hover:text-brand-500 dark:text-gray-300">{{ $item->name }}
                                <span class="block text-xs text-gray-400">{{ $item->sku }}</span></a>
                            <x-admin.badge size="sm" :color="$item->stock_quantity === 0 ? 'error' : 'warning'">
                                {{ $item->stock_quantity }}
                            </x-admin.badge>
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
