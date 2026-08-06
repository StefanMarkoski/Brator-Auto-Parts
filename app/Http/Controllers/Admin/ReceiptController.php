<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Ordering\Actions\ChangeReceiptStatusAction;
use App\Domain\Ordering\Enums\ReceiptStatus;
use App\Domain\Ordering\Models\Receipt;
use App\Support\Database\LikePattern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * The staff receipts list Stefan asked for. It lives HERE, behind the admin login,
 * rather than on the storefront: an unauthenticated index would expose every
 * customer's name, email address and delivery address.
 */
final class ReceiptController
{
    public function __construct(private ChangeReceiptStatusAction $changeStatus) {}

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
                ->orderBy('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function show(string $receipt): View
    {
        return view('admin.pages.receipt-detail', [
            'receipt' => Receipt::query()->with('lines')->findOrFail($receipt),
            'statuses' => ReceiptStatus::cases(),
        ]);
    }

    public function updateStatus(Request $request, string $receipt): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,paid,cancelled'],
        ]);

        $model = Receipt::query()->with('lines')->findOrFail($receipt);
        $to = ReceiptStatus::from($validated['status']);
        // Captured before the action runs. Reading $model->status afterwards would happen to
        // work — the action updates its own locked instance, not this one — but relying on
        // one copy of a row being stale is the kind of thing that breaks the day somebody
        // adds a refresh().
        $from = $model->status;

        try {
            $changed = $this->changeStatus->execute($model, $to, $request->user()?->id);
        } catch (RuntimeException $e) {
            // Reinstating a cancelled order whose stock has since sold to someone else.
            // The transaction rolled back, so the receipt is untouched — say why rather
            // than showing a green "saved" over a change that did not happen.
            return redirect()
                ->route('admin.receipts.show', $model->id)
                ->with('error', $e->getMessage());
        }

        if (! $changed) {
            return redirect()
                ->route('admin.receipts.show', $model->id)
                ->with('status', "This receipt is already {$to->label()}.");
        }

        return redirect()
            ->route('admin.receipts.show', $model->id)
            ->with('status', match ($to) {
                ReceiptStatus::Cancelled => 'Cancelled, and every item on it went back into stock.',
                ReceiptStatus::Paid => $from === ReceiptStatus::Cancelled
                    ? 'Reinstated as paid, and the items were taken back out of stock.'
                    : 'Marked as paid.',
                ReceiptStatus::Pending => 'Marked as pending.',
            });
    }

    public function updateNotes(Request $request, string $receipt): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // notes is one of exactly two columns the seal leaves writable, alongside status.
        Receipt::query()->findOrFail($receipt)->update(['notes' => $validated['notes'] ?? null]);

        return redirect()->route('admin.receipts.show', $receipt)->with('status', 'Note saved.');
    }
}
