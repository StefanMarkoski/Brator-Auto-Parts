<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Ordering\Enums\ReceiptStatus;
use App\Domain\Ordering\Models\Receipt;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class DashboardController
{
    public function __invoke(): View
    {
        $paid = Receipt::query()->where('status', ReceiptStatus::Paid);

        return view('admin.pages.dashboard', [
            'receiptsTotal' => (clone $paid)->count(),
            'receiptsThisMonth' => (clone $paid)->where('placed_at', '>=', now()->startOfMonth())->count(),
            'revenue' => Money::fromMinor((int) (clone $paid)->sum('total_minor')),
            'vatCollected' => Money::fromMinor((int) (clone $paid)->sum('vat_minor')),
            'productsActive' => DB::table('products')->where('is_active', true)->whereNull('deleted_at')->count(),
            'productsOutOfStock' => DB::table('products')
                ->where('is_active', true)->whereNull('deleted_at')
                ->where('stock_status', 'out_of_stock')->count(),
            'fitmentRows' => DB::table('product_vehicle_fitments')->count(),
            'overriddenFields' => DB::table('product_field_overrides')->count(),
            'latestReceipts' => Receipt::query()->with('lines')
                ->orderByDesc('placed_at')->limit(8)->get(),
            'lowStock' => DB::table('products')
                ->where('is_active', true)->whereNull('deleted_at')
                ->where('stock_quantity', '<=', 5)
                ->orderBy('stock_quantity')
                ->limit(8)
                ->get(['name', 'sku', 'stock_quantity']),
        ]);
    }
}
