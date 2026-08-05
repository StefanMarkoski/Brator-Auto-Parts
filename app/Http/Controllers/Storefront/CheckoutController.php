<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Ordering\Actions\PlaceReceiptAction;
use App\Domain\Ordering\Http\Requests\PlaceReceiptRequest;
use App\Domain\Ordering\Models\Receipt;
use App\Domain\Ordering\Services\BasketResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CheckoutController
{
    public function __construct(
        private BasketResolver $baskets,
        private PlaceReceiptAction $placeAction,
    ) {}

    /**
     * The fake payment step. There is no gateway — the receipt is the deliverable.
     */
    public function place(PlaceReceiptRequest $request): RedirectResponse
    {
        $basket = $this->baskets->current();

        if ($basket === null || $basket->lines->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        try {
            $receipt = $this->placeAction->execute($basket, $request->toDTO());
        } catch (RuntimeException $e) {
            return redirect()->route('cart')->with('error', $e->getMessage());
        }

        return redirect()->route('receipt', $receipt->id);
    }

    /**
     * The confirmation page, addressed by the receipt's ULID.
     *
     * Deliberately NOT a /receipts index: listing every receipt without authentication
     * would expose every customer's name, email and address. The staff-facing list
     * arrives with the admin panel and its login in phase 6.
     */
    public function show(string $receipt): View
    {
        $model = Receipt::query()->with('lines')->find($receipt);

        if ($model === null) {
            throw new NotFoundHttpException('No such receipt.');
        }

        return view('shop.receipt', [
            'receipt' => $model,
            'breadcrumbs' => ["Receipt {$model->receipt_number}" => null],
        ]);
    }
}
