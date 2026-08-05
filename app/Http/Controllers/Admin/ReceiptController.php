<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Ordering\Models\Receipt;
use App\Support\Database\LikePattern;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The staff receipts list Stefan asked for. It lives HERE, behind the admin login,
 * rather than on the storefront: an unauthenticated index would expose every
 * customer's name, email address and delivery address.
 */
final class ReceiptController
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('admin.pages.receipts', [
            'search' => $search,
            'status' => $request->query('status'),
            'receipts' => Receipt::query()
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('receipt_number', 'like', LikePattern::contains($search))
                    ->orWhere('customer_name', 'like', LikePattern::contains($search))
                    ->orWhere('customer_email', 'like', LikePattern::contains($search))))
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->withCount('lines')
                ->orderByDesc('placed_at')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function show(string $receipt): View
    {
        return view('admin.pages.receipt-detail', [
            'receipt' => Receipt::query()->with('lines')->findOrFail($receipt),
        ]);
    }
}
