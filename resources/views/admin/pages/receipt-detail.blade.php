@extends('admin.layouts.admin')
@section('title', 'Receipt '.$receipt->receipt_number)
@section('heading', 'Receipt '.$receipt->receipt_number)

@section('content')
    @use('App\Domain\Ordering\Enums\ReceiptStatus')

    <x-admin.page-breadcrumb :pageTitle="'Receipt '.$receipt->receipt_number" />

    @if (session('status'))
        <x-admin.alert variant="success" class="mb-6">{{ session('status') }}</x-admin.alert>
    @endif

    @if (session('error'))
        <x-admin.alert variant="error" title="Nothing was changed" class="mb-6">{{ session('error') }}</x-admin.alert>
    @endif

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
                    @if (! $receipt->discount_minor->isZero())
                        <div class="flex justify-between"><dt class="text-gray-500">Discount ({{ $receipt->coupon_code }})</dt><dd>−{{ $receipt->discount_minor->format() }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-gray-500">VAT</dt><dd>{{ $receipt->vat_minor->format() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Delivery</dt><dd>{{ $receipt->shipping_minor->isZero() ? 'Free' : $receipt->shipping_minor->format() }}</dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-2 font-semibold dark:border-gray-800">
                        <dt>Total</dt><dd>{{ $receipt->total_minor->format() }}</dd></div>
                </dl>
            </x-admin.component-card>
        </div>

        <div class="space-y-6">
            <x-admin.component-card title="Status"
                desc="Cancelling puts every item on this receipt back into stock. The totals never change — a receipt records what happened.">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Currently</span>
                    <x-admin.badge :color="match ($receipt->status) {
                        ReceiptStatus::Paid => 'success',
                        ReceiptStatus::Cancelled => 'error',
                        default => 'warning',
                    }">{{ $receipt->status->label() }}</x-admin.badge>
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach ($statuses as $case)
                        @continue($case === $receipt->status)

                        @if ($case === ReceiptStatus::Cancelled)
                            {{-- The one transition with a physical consequence, so it asks first. --}}
                            <x-admin.confirm-action
                                :action="route('admin.receipts.status', $receipt->id, false)"
                                method="PUT"
                                label="Cancel order"
                                :title="'Cancel receipt '.$receipt->receipt_number.'?'"
                                :message="'Every item on it goes back into stock, and the movement is recorded against this receipt. The totals stay as they are.'"
                                confirm="Yes, cancel it">
                                <input type="hidden" name="status" value="cancelled" />
                            </x-admin.confirm-action>
                        @else
                            <form method="post" action="{{ route('admin.receipts.status', $receipt->id, false) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="status" value="{{ $case->value }}" />
                                <x-admin.button type="submit" variant="outline" size="sm">
                                    @if ($receipt->status === ReceiptStatus::Cancelled)
                                        Reinstate as {{ strtolower($case->label()) }}
                                    @else
                                        Mark {{ strtolower($case->label()) }}
                                    @endif
                                </x-admin.button>
                            </form>
                        @endif
                    @endforeach
                </div>

                @if ($receipt->status === ReceiptStatus::Cancelled)
                    <p class="text-xs text-gray-400">
                        Reinstating takes the items back out of stock. If any of them has since
                        sold to somebody else, it will be refused rather than overselling.
                    </p>
                @endif
            </x-admin.component-card>

            <x-admin.component-card title="Customer">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-gray-500 dark:text-gray-400">Name</dt><dd class="text-gray-800 dark:text-white/90">{{ $receipt->customer_name }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd><a href="mailto:{{ $receipt->customer_email }}" class="text-brand-500 hover:underline">{{ $receipt->customer_email }}</a></dd></div>
                    @if ($receipt->customer_phone)
                        <div><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $receipt->customer_phone) }}" class="text-brand-500 hover:underline">{{ $receipt->customer_phone }}</a></dd></div>
                    @endif
                    <div><dt class="text-gray-500 dark:text-gray-400">Delivery address</dt>
                        <dd class="whitespace-pre-line text-gray-800 dark:text-white/90">{{ $receipt->shipping_address }}</dd></div>
                </dl>
            </x-admin.component-card>

            <x-admin.component-card title="Notes"
                desc="Notes and status are the only two things on a receipt that can change.">
                <form method="post" action="{{ route('admin.receipts.notes', $receipt->id, false) }}"
                    class="space-y-3">
                    @csrf
                    @method('PUT')
                    <x-admin.textarea name="notes" rows="4"
                        placeholder="What the customer asked for, what you told them.">{{ old('notes', $receipt->notes) }}</x-admin.textarea>
                    <x-admin.button type="submit" variant="outline" size="sm">Save note</x-admin.button>
                </form>
            </x-admin.component-card>
        </div>
    </div>
@endsection
