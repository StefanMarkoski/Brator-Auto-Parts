<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Queries\Internal\GetProductDetailQuery;
use App\Domain\Catalog\Queries\Internal\ListProductCardsQuery;
use App\Domain\Catalog\Services\RecentlyViewed;
use App\Domain\Fitment\Queries\Public\GetProductIdsForVehicleQuery;
use App\Domain\Fitment\Services\VehicleSelection;
use App\Domain\Ordering\Queries\Public\GetFrequentlyBoughtTogetherQuery;
use App\Support\ValueObjects\Money;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductController
{
    public function __construct(
        private GetProductDetailQuery $detail,
        private ListProductCardsQuery $cards,
        private RecentlyViewed $recentlyViewed,
        private VehicleSelection $vehicle,
        private GetProductIdsForVehicleQuery $fitment,
        // Ordering's own read API. Receipts are its data; Catalog may only ask through it.
        private GetFrequentlyBoughtTogetherQuery $boughtWith,
    ) {}

    public function show(string $slug): View
    {
        $product = $this->detail->bySlug($slug);

        if ($product === null) {
            throw new NotFoundHttpException("No active product with slug [{$slug}].");
        }

        // Read the history BEFORE recording this visit, or the strip always leads
        // with the page you are already on.
        $recent = $this->cards->forIds($this->recentlyViewed->all($product->id));
        $this->recentlyViewed->remember($product->id);

        /*
         | REAL co-purchase data, not the seeded product_recommendations table this used to read.
         | That table made the strip a convincing-looking widget presenting invented pairings —
         | the same class of thing as the theme's fake prices. This counts receipt lines: the
         | other parts on the receipts this part appears on, most-shared first.
         |
         | Sparse and honest about it: 590 of 5,000 products have a companion today, so most
         | pages get nothing and the section is hidden rather than claiming a pairing that has
         | never happened.
        */
        $companionIds = $this->boughtWith->execute($product->id, 5);

        /*
         | The bundle is the current part FIRST, then its companions — one uniform list, because
         | the theme's markup is a row of identical cards each carrying its own checkbox. The
         | first version rendered the current part as a bare <label> beside the cards, which is
         | why the section looked broken: the theme's checkbox styling is scoped to a checkbox
         | INSIDE a card, so a label outside one gets none of it.
         |
         | Empty when there are no companions: a bundle of one is not a bundle.
        */
        $bundle = $companionIds === []
            ? collect()
            : $this->cards->forIds([$product->id, ...$companionIds]);

        $primary = $product->categories->firstWhere('pivot.is_primary', true)
            ?? $product->categories->first();

        /*
         | Three states, not two. The page used to claim "This product fit for your vehicle"
         | unconditionally — no chosen car, wrong car, did not matter.
         |
         |   null  no vehicle chosen, so say nothing about fitment
         |   true  the chosen vehicle is in this product's fitment list
         |   false the chosen vehicle is NOT, and the shopper needs telling
        */
        $chosenVariant = $this->vehicle->current();

        $fitsChosenVehicle = $chosenVariant === null
            ? null
            : $this->fitment->fits($product->id, $chosenVariant);

        return view('shop.product', [
            'product' => $product,
            'breadcrumbs' => array_filter([
                $primary?->name => $primary === null
                    ? null
                    : route('shop.category', $primary->slug, false),
                $product->name => null,
            ], fn ($_, $label) => $label !== '', ARRAY_FILTER_USE_BOTH),
            // The two recommendation blocks the theme already ships.
            'bundle' => $bundle,
            'similar' => $this->detail->recommendations($product->id, 'similar', 5),
            'fitments' => $this->detail->fitments($product->id),
            'fitsChosenVehicle' => $fitsChosenVehicle,
            'chosenVehicleName' => $this->vehicle->picker()['name'],
            'recentlyViewed' => $recent,
            // Rendered server-side too, so the figure is correct before Alpine boots.
            // Summed over the bundle itself, which now includes the current part — so the total
            // and the ticked boxes can never disagree about what is in it.
            'bundleTotal' => $bundle->reduce(
                fn (Money $carry, $card) => $carry->add($card->price),
                Money::zero()
            ),
        ]);
    }
}
