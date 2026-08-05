<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Queries\Internal\GetProductDetailQuery;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductController
{
    public function __construct(private GetProductDetailQuery $detail) {}

    public function show(string $slug): View
    {
        $product = $this->detail->bySlug($slug);

        if ($product === null) {
            throw new NotFoundHttpException("No active product with slug [{$slug}].");
        }

        return view('shop.product', [
            'product' => $product,
            // The two recommendation blocks the theme already ships.
            'boughtTogether' => $this->detail->recommendations($product->id, 'bought_together', 3),
            'similar' => $this->detail->recommendations($product->id, 'similar', 5),
            'fitments' => $this->detail->fitments($product->id),
        ]);
    }
}
