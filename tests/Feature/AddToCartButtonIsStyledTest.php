<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Product;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\FitmentSeederSmall;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\MerchandisingSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The product card's Add to cart button has to look like a button.
 *
 * THE BUG. The theme paints this control with
 *
 *   .…item-area .…item-mini .brator-product-single-item-btn a
 *
 * — an ANCHOR, because the theme's own card only ever links to the product page. Ours has to POST,
 * so it is a <button> in a <form>, and that one element-name difference meant the selector never
 * matched a thing. Measured in a browser: rgb(239,239,239) grey on black, 80x21px, an outset
 * border, sitting inside a 44px slot, while every other button in the shop is #f73312 orange.
 * Stefan's words were "no style at all", and he was looking at the busiest control in the shop —
 * it is on every card of every listing, strip and recommendation block.
 *
 * WHY THIS TEST EXISTS AT ALL. The button now leans on button-fill-one, which is the theme's own
 * class and the first selector in that same grouped rule, plus the anchor rule's overrides copied
 * inline. Both are easy to lose while tidying markup, and losing either puts the grey browser
 * button back with nothing failing anywhere. A PHP test cannot see a colour, but it can see
 * whether the class that carries the colour is still on the element.
 *
 * VERIFIED IN A REAL BROWSER, which is the part this file cannot do: background
 * rgb(247, 51, 18), white text, 254x40px, filling its slot — the same paint as the Add To Cart on
 * the product page and the one in the list view.
 */
final class AddToCartButtonIsStyledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogStructureSeeder::class);
        $this->seed(ProductSeederSmall::class);
        $this->seed(FitmentSeederSmall::class);
        $this->seed(MerchandisingSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(ContentSeeder::class);
    }

    public function test_every_card_button_carries_the_themes_own_fill_class(): void
    {
        // The homepage strips and the category listing both render through
        // partials/product-card.blade.php, so both are checked: an @include is easy to fork by
        // accident and hard to notice.
        foreach (['/', $this->listingUrl()] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            /*
             | Scoped to the GRID card, which is `…item-btn"> <form>`. The list view's card
             | (partials/product-card-list.blade.php) puts a .brator-product-single-item-btn-cart
             | div in between, and its button IS matched by a theme selector of its own — it never
             | had this bug and must not be dragged into this assertion.
            */
            preg_match_all(
                '/<div class="brator-product-single-item-btn">\s*<form[^>]*>.*?<button[^>]*>/s',
                $html,
                $matches
            );

            $this->assertNotEmpty($matches[0], "No card Add to cart button rendered on {$path}.");

            foreach ($matches[0] as $block) {
                $this->assertStringContainsString('button-fill-one', $block,
                    "A card Add to cart button on {$path} has lost button-fill-one, so it renders "
                    ."as an unstyled grey browser button:\n  ".$block);
                $this->assertStringContainsString('width: 100%', $block,
                    "A card Add to cart button on {$path} has lost the theme's own width override, "
                    .'so it no longer fills its slot.');
            }
        }
    }

    public function test_an_out_of_stock_card_says_so_and_is_dimmed(): void
    {
        /*
         | The theme ships no disabled state for this button. Left undimmed, an out-of-stock part
         | got a full-strength orange button that silently refused to be pressed — which reads as a
         | broken shop rather than a sold-out part.
        */
        Product::query()->update(['stock_status' => StockStatus::OutOfStock, 'stock_quantity' => 0]);

        $html = $this->get($this->listingUrl())->assertOk()->getContent();

        $this->assertStringContainsString('Out of stock', $html,
            'An out-of-stock card still offers "Add to cart".');
        $this->assertStringContainsString('opacity: 0.55', $html,
            'The disabled Add to cart button is painted like a working one.');
    }

    /** The grid listing lives under a category; bare /shop is the category index. */
    private function listingUrl(): string
    {
        $product = Product::query()->has('categories')->firstOrFail();

        return '/shop/'.$product->categories()->firstOrFail()->slug;
    }
}
