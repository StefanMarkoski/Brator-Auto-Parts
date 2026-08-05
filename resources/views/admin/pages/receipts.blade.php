@extends('admin.layouts.admin')
@section('title', 'Receipts')
@section('heading', 'Receipts')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Receipts" />

    <x-admin.component-card title="All receipts" desc="Behind the staff login — these rows carry customer names, emails and addresses.">
        <form method="get" class="mb-5 flex flex-wrap gap-3">
            <input type="search" name="q" value="{{ $search }}" placeholder="Receipt number, name or email"
                class="h-11 flex-1 min-w-64 rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:text-white/90" />
            <select name="status" x-on:change="$el.form.requestSubmit()"
                class="h-11 rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:text-white/90">
                <option value="">Any status</option>
                @foreach (['paid' => 'Paid', 'pending' => 'Pending', 'cancelled' => 'Cancelled'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600">Search</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-400">
                    <tr>
                        <th class="pb-3 pr-4">Receipt</th>
                        <th class="pb-3 pr-4">Placed</th>
                        <th class="pb-3 pr-4">Customer</th>
                        <th class="pb-3 pr-4">Items</th>
                        <th class="pb-3 pr-4">Status</th>
                        <th class="pb-3 pr-4 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($receipts as $receipt)
                        <tr>
                            <td class="py-3 pr-4">
                                <a href="{{ route('admin.receipts.show', $receipt->id, false) }}"
                                    class="font-medium text-brand-500 hover:underline">{{ $receipt->receipt_number }}</a>
                            </td>
                            <td class="py-3 pr-4 text-gray-500">{{ $receipt->placed_at?->format('d M Y H:i') }}</td>
                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">{{ $receipt->customer_name }}
                                <span class="block text-xs text-gray-400">{{ $receipt->customer_email }}</span></td>
                            <td class="py-3 pr-4 text-gray-500">{{ $receipt->lines_count }}</td>
                            <td class="py-3 pr-4">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $receipt->status->value === 'paid' ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300' }}">
                                    {{ $receipt->status->label() }}
                                </span>
                            </td>
                            <td class="py-3 pr-4 text-right font-medium text-gray-800 dark:text-white/90">{{ $receipt->total_minor->format() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-400">No receipts match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $receipts->links() }}</div>
    </x-admin.component-card>
@endsection
