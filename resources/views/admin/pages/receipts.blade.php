@extends('admin.layouts.admin')
@section('title', 'Receipts')
@section('heading', 'Receipts')

@section('content')
    @use('App\Domain\Ordering\Enums\ReceiptStatus')

    <x-admin.page-breadcrumb pageTitle="Receipts" />

    <x-admin.component-card title="All receipts" desc="Behind the staff login — these rows carry customer names, emails and addresses.">
        <form method="get" class="mb-5 flex flex-wrap gap-3">
            <x-admin.input type="search" name="q" :value="$search"
                placeholder="Receipt number, name or email" class="flex-1 min-w-64" />

            <x-admin.select name="status" x-on:change="$el.form.requestSubmit()"
                :options="['paid' => 'Paid', 'pending' => 'Pending', 'cancelled' => 'Cancelled']"
                :selected="$status" placeholder="Any status" class="w-44" />

            <x-admin.button type="submit" size="sm">Search</x-admin.button>
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
                            {{-- Cancelled reads as error, not as the same grey as pending:
                                 those two mean opposite things to whoever is looking. --}}
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
                        <tr><td colspan="6" class="py-6 text-center text-gray-400">No receipts match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $receipts->links() }}</div>
    </x-admin.component-card>
@endsection
