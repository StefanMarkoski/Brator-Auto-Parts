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
        $paid = fn () => Receipt::query()->where('status', ReceiptStatus::Paid);

        $thisMonth = now()->startOfMonth();

        /*
         | Month-on-month, computed rather than decorative.
         |
         | TailAdmin's metric cards ship with a hardcoded "+11.01%" pill, and it is very
         | tempting to keep it because it makes the card look finished. A number on a
         | dashboard that is not measuring anything is the same class of lie as the theme's
         | "1 - 40 of 1,652 results" — worse, because a trend invites a decision.
         |
         | LIKE FOR LIKE, and this matters. Comparing month-to-date against the whole of last
         | month showed revenue "down 73%" on the sixth of August, which is arithmetically
         | true and completely misleading: six days cannot lose to thirty-one. The comparison
         | window is the same number of days into the previous month, so the two figures are
         | actually comparable.
         |
         | The trend is null when there is no previous period at all, and the card omits the
         | pill entirely rather than showing 0%.
        */
        // Elapsed seconds, taken from the start of the month FORWARD. Written the other way
        // round ($now->diffInDays($start)) Carbon returns a negative, the comparison window
        // ends before it begins, and the trend silently disappears instead of being wrong in
        // a way anyone would notice.
        $elapsed = $thisMonth->diffInSeconds(now());

        $lastMonthStart = $thisMonth->copy()->subMonthNoOverflow();
        $lastMonthSamePoint = $lastMonthStart->copy()->addSeconds((int) $elapsed);

        $revenueThisMonth = (int) $paid()->where('placed_at', '>=', $thisMonth)->sum('total_minor');
        $revenueLastMonth = (int) $paid()
            ->whereBetween('placed_at', [$lastMonthStart, $lastMonthSamePoint])
            ->sum('total_minor');

        $receiptsThisMonth = $paid()->where('placed_at', '>=', $thisMonth)->count();
        $receiptsLastMonth = $paid()
            ->whereBetween('placed_at', [$lastMonthStart, $lastMonthSamePoint])
            ->count();

        return view('admin.pages.dashboard', [
            'receiptsTotal' => $paid()->count(),
            'receiptsThisMonth' => $receiptsThisMonth,
            'receiptsTrend' => $this->percentChange($receiptsLastMonth, $receiptsThisMonth),
            'revenue' => Money::fromMinor((int) $paid()->sum('total_minor')),
            'revenueThisMonth' => Money::fromMinor($revenueThisMonth),
            'revenueTrend' => $this->percentChange($revenueLastMonth, $revenueThisMonth),
            'vatCollected' => Money::fromMinor((int) $paid()->sum('vat_minor')),
            'cancelledCount' => Receipt::query()->where('status', ReceiptStatus::Cancelled)->count(),
            'productsActive' => DB::table('products')->where('is_active', true)->whereNull('deleted_at')->count(),
            'productsOutOfStock' => DB::table('products')
                ->where('is_active', true)->whereNull('deleted_at')
                ->where('stock_status', 'out_of_stock')->count(),
            'unpublishedCount' => DB::table('products')
                ->whereNull('deleted_at')->whereNull('published_at')->count(),
            'fitmentRows' => DB::table('product_vehicle_fitments')->count(),
            'overriddenFields' => DB::table('product_field_overrides')->count(),
            'latestReceipts' => Receipt::query()->withCount('lines')
                ->orderByDesc('placed_at')->orderBy('id')->limit(8)->get(),
            'lowStock' => DB::table('products')
                ->where('is_active', true)->whereNull('deleted_at')
                ->where('stock_quantity', '<=', 5)
                ->orderBy('stock_quantity')
                ->orderBy('id')
                ->limit(8)
                ->get(['id', 'name', 'sku', 'stock_quantity']),
            'revenueSeries' => $this->revenueByMonth(),
        ]);
    }

    /**
     * Paid revenue per month for the last twelve months, in major units.
     *
     * Grouped in SQL and then filled in PHP: a month with no orders returns no row, and a
     * chart that silently skips empty months draws a rising line through a dead patch.
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    private function revenueByMonth(): array
    {
        $rows = Receipt::query()
            ->where('status', ReceiptStatus::Paid)
            ->where('placed_at', '>=', now()->subMonthsNoOverflow(11)->startOfMonth())
            ->selectRaw("DATE_FORMAT(placed_at, '%Y-%m') as month, SUM(total_minor) as total")
            ->groupBy('month')
            ->pluck('total', 'month')
            ->all();

        $labels = [];
        $values = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonthsNoOverflow($i)->startOfMonth();
            $key = $month->format('Y-m');

            $labels[] = $month->format('M');
            $values[] = round(((int) ($rows[$key] ?? 0)) / 100, 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Null when there is nothing to compare against, so the card can omit the pill. */
    private function percentChange(int $before, int $after): ?float
    {
        if ($before === 0) {
            return null;
        }

        return round((($after - $before) / $before) * 100, 2);
    }
}
