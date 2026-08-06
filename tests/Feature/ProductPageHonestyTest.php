<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductReview;
use App\Domain\Fitment\Models\VehicleVariant;
use App\Domain\Fitment\Services\VehicleSelection;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\FitmentSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The product page must not claim things that are not true.
 *
 * Every assertion here corresponds to a piece of the purchased theme's demo copy that was
 * rendering as though it were this shop's data. The dangerous one is fitment: telling
 * somebody a part fits their car when it does not is the one mistake a parts shop cannot
 * make, and the page used to say it unconditionally.
 */
final class ProductPageHonestyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class, FitmentSeeder::class]);
    }

    public function test_no_vehicle_chosen_means_no_claim_about_fitment(): void
    {
        $product = Product::query()->firstOrFail();

        $this->get("/product/{$product->slug}")
            ->assertOk()
            ->assertDontSee('Fits your', false)
            ->assertDontSee('Not listed as fitting your', false);
    }

    public function test_a_part_that_fits_the_chosen_vehicle_says_so(): void
    {
        [$product, $variant] = $this->fittingPair();

        $this->withSession([VehicleSelection::SESSION_KEY => $variant])
            ->get("/product/{$product->slug}")
            ->assertOk()
            ->assertSee('Fits your', false)
            ->assertDontSee('Not listed as fitting your', false);
    }

    public function test_a_part_that_does_not_fit_the_chosen_vehicle_says_that_too(): void
    {
        [$product, $variant] = $this->fittingPair();

        // A variant this product is NOT listed against.
        $otherVariant = (int) DB::table('vehicle_variants')
            ->whereNotIn('id', function ($q) use ($product) {
                $q->select('vehicle_variant_id')
                    ->from('product_vehicle_fitments')
                    ->where('product_id', $product->id);
            })
            ->value('id');

        $this->assertNotSame(0, $otherVariant, 'This test needs a variant the product does not fit.');
        $this->assertNotSame($variant, $otherVariant);

        // The whole point. Before this, the page told a Golf owner that a Sprinter part fits.
        $this->withSession([VehicleSelection::SESSION_KEY => $otherVariant])
            ->get("/product/{$product->slug}")
            ->assertOk()
            ->assertSee('Not listed as fitting your', false);
    }

    public function test_the_brand_shown_is_the_products_own(): void
    {
        $product = Product::query()->whereNotNull('brand_id')->with('brand')->firstOrFail();

        $this->get("/product/{$product->slug}")
            ->assertOk()
            ->assertSee($product->brand->name, false)
            // The theme's demo brand, printed on every product.
            ->assertDontSee('Sparegold', false);
    }

    public function test_the_review_count_and_rating_are_the_products_own(): void
    {
        $product = Product::query()->firstOrFail();
        $product->reviews()->delete();
        $product->update(['reviews_count' => 0, 'rating_avg' => 0]);

        $html = $this->get("/product/{$product->slug}")->assertOk()->getContent();

        // "Reviews (14)" and "4.5/5" were hardcoded, so a part nobody had reviewed still
        // advertised fourteen reviews and a good score.
        $this->assertStringNotContainsString('Reviews (14)', $html);
        $this->assertStringNotContainsString('4.5/5', $html);
        $this->assertStringContainsString('Reviews (0)', $html);
        $this->assertStringContainsString('Not rated', $html);
    }

    public function test_real_reviews_are_shown_and_the_demo_ones_are_gone(): void
    {
        $product = Product::query()->firstOrFail();
        $product->reviews()->delete();

        ProductReview::create([
            'product_id' => $product->id,
            'author_name' => 'Marko Petrov',
            'author_email' => 'marko@example.com',
            'rating' => 5,
            'title' => 'Fitted my Golf perfectly',
            'body' => 'Arrived in two days and matched the original part exactly.',
            'is_approved' => true,
        ]);

        $product->update(['reviews_count' => 1, 'rating_avg' => 5]);

        $this->get("/product/{$product->slug}")
            ->assertOk()
            ->assertSee('Marko Petrov', false)
            ->assertSee('Fitted my Golf perfectly', false)
            // Three identical hardcoded reviews, all by the same footballer, praising a
            // holiday cottage.
            ->assertDontSee('Paulo Dybala', false)
            ->assertDontSee('Accommodation, fantastic', false);
    }

    public function test_an_unapproved_review_is_not_shown(): void
    {
        $product = Product::query()->firstOrFail();
        $product->reviews()->delete();

        ProductReview::create([
            'product_id' => $product->id,
            'author_name' => 'Spam Bot',
            'author_email' => 'spam@example.com',
            'rating' => 1,
            'title' => 'Buy cheap watches',
            'body' => 'Click here.',
            'is_approved' => false,
        ]);

        $this->get("/product/{$product->slug}")
            ->assertOk()
            ->assertDontSee('Buy cheap watches', false);
    }

    public function test_the_description_tab_does_not_describe_alloy_wheels(): void
    {
        $product = Product::query()->firstOrFail();

        $html = $this->get("/product/{$product->slug}")->assertOk()->getContent();

        // The theme's demo copy promised a TPMS-compatible hub centering ring and a
        // five-year structural warranty from "TSW" — on brake fluid, among other things.
        foreach (['Plastic Hub Centering Ring', 'TSW provides', 'TPMS', 'finish warranty'] as $fiction) {
            $this->assertStringNotContainsString($fiction, $html,
                "The theme's alloy-wheel copy is still rendering: {$fiction}");
        }
    }

    public function test_no_link_on_the_product_page_goes_nowhere(): void
    {
        $product = Product::query()->firstOrFail();

        $html = $this->get("/product/{$product->slug}")->assertOk()->getContent();

        // Tags and part numbers were href="#_" — a link that looks clickable and is not.
        // The theme uses #_ for every placeholder link, so counting them is a decent proxy
        // for "how much of this page is still decoration".
        $deadLinks = preg_match_all('/href="#_"/', $html);

        $this->assertLessThanOrEqual(12, $deadLinks,
            "There are {$deadLinks} placeholder links left on the product page.");
    }

    /**
     * A product and a vehicle variant it genuinely fits.
     *
     * @return array{0: Product, 1: int}
     */
    private function fittingPair(): array
    {
        $row = DB::table('product_vehicle_fitments')->first();

        $this->assertNotNull($row, 'This test needs seeded fitment data.');

        $product = Product::query()->visible()->findOrFail($row->product_id);

        $this->assertNotNull(VehicleVariant::query()->find($row->vehicle_variant_id));

        return [$product, (int) $row->vehicle_variant_id];
    }
}
