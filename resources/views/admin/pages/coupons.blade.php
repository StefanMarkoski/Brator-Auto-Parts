@extends('admin.layouts.admin')
@section('title', 'Coupons')
@section('heading', 'Coupons')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Coupons" />

    @if (session('status'))
        <x-admin.alert variant="success" class="mb-6">{{ session('status') }}</x-admin.alert>
    @endif

    @if (session('error'))
        <x-admin.alert variant="warning" class="mb-6">{{ session('error') }}</x-admin.alert>
    @endif

    @if ($errors->any())
        <x-admin.alert variant="error" title="Check the form" class="mb-6">{{ $errors->first() }}</x-admin.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-admin.component-card title="Discount codes" :desc="number_format($coupons->total()).' codes'">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-400">
                            <tr>
                                <th class="pb-3 pr-4">Code</th>
                                <th class="pb-3 pr-4">Discount</th>
                                <th class="pb-3 pr-4">Applies to</th>
                                <th class="pb-3 pr-4 text-right">Used</th>
                                <th class="pb-3 pr-4">Status</th>
                                <th class="pb-3 pr-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($coupons as $coupon)
                                <tr>
                                    <td class="py-3 pr-4">
                                        <code class="font-medium text-gray-800 dark:text-white/90">{{ $coupon->code }}</code>
                                    </td>
                                    <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">{{ $coupon->discount_percent }}%</td>
                                    <td class="py-3 pr-4 text-gray-500">
                                        {{ $coupon->hasMinimum()
                                            ? 'Orders over '.$coupon->minimum_order_minor->format()
                                            : 'Any order' }}
                                    </td>
                                    <td class="py-3 pr-4 text-right {{ $coupon->times_used > 0 ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400' }}">
                                        {{ number_format($coupon->times_used) }}
                                    </td>
                                    <td class="py-3 pr-4">
                                        <x-admin.badge size="sm" :color="$coupon->is_active ? 'success' : 'light'">
                                            {{ $coupon->is_active ? 'Live' : 'Off' }}
                                        </x-admin.badge>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <div class="flex items-center justify-end gap-3">
                                            {{-- Its own form per row, all siblings — no nesting. --}}
                                            <form method="post"
                                                action="{{ route('admin.coupons.update', $coupon->id, false) }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="is_active" value="{{ $coupon->is_active ? 0 : 1 }}" />
                                                <button type="submit"
                                                    class="text-sm font-medium text-brand-500 hover:underline">{{ $coupon->is_active ? 'Switch off' : 'Switch on' }}</button>
                                            </form>

                                            <x-admin.confirm-action
                                                :action="route('admin.coupons.destroy', $coupon->id, false)"
                                                method="DELETE"
                                                label="Delete"
                                                trigger-class="text-sm font-medium text-error-600 hover:underline dark:text-error-400"
                                                :disabled="$coupon->times_used > 0"
                                                :disabled-reason="'Used on '.$coupon->times_used.' order(s). Switch it off instead — deleting it would leave nothing to explain the discount on those receipts.'"
                                                :title="'Delete '.$coupon->code.'?'"
                                                message="It has never been used, so nothing refers to it."
                                                confirm="Yes, delete it" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-gray-400">
                                        No codes yet. Generate one on the right.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">{{ $coupons->links() }}</div>
            </x-admin.component-card>
        </div>

        <div class="space-y-6">
            <x-admin.component-card title="Generate a code"
                desc="The code is generated, not typed — ten characters, readable over the phone.">
                <form method="post" action="{{ route('admin.coupons.store', [], false) }}" class="space-y-5">
                    @csrf

                    <x-admin.field label="Discount" name="discount_percent" :required="true"
                        hint="Whole percent, 1 to 100. Taken off the goods before VAT.">
                        <x-admin.input type="number" name="discount_percent" min="1" max="100" step="1"
                            :value="old('discount_percent', 10)" required />
                    </x-admin.field>

                    <x-admin.field label="Minimum order (optional)" name="minimum_order_major"
                        hint="Leave blank and it applies to any basket. Set it and the code only discounts once the goods reach that much, excluding VAT.">
                        <x-admin.input type="number" name="minimum_order_major" min="0" step="0.01"
                            :value="old('minimum_order_major')" placeholder="3000.00" />
                    </x-admin.field>

                    <x-admin.button type="submit">Generate code</x-admin.button>
                </form>
            </x-admin.component-card>

            <x-admin.component-card title="How a code behaves">
                <ul class="space-y-2 text-xs text-gray-500 dark:text-gray-400">
                    <li>The discount comes off the goods, and VAT is then charged on what remains —
                        so the shop does not pay VAT on money it never took.</li>
                    <li>Free delivery is judged on the discounted amount. A 3.100 basket with 10% off
                        is 2.790 spent, so delivery is charged.</li>
                    <li>A code below its minimum stays in the basket and discounts nothing, and the
                        cart says how much more is needed.</li>
                    <li>Switching a code off stops it discounting immediately, including in baskets
                        that already hold it.</li>
                    <li>The code and the amount are written onto the receipt, so an old order still
                        explains itself after the code is changed or removed.</li>
                </ul>
            </x-admin.component-card>
        </div>
    </div>
@endsection
