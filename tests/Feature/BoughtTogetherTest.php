<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Enums\ReceiptStatus;
use App\Domain\Ordering\Queries\Public\GetFrequentlyBoughtTogetherQuery;
use App\Support\ValueObjects\Money;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Frequently Bought Together", from receipts rather than from a seeded table.
 *
 * It used to read `product_recommendations`, which the seeder filled — so the strip was a
 * convincing-looking widget presenting pairings that had never happened. It is now a count over
 * receipt lines, which means it is sometimes empty, and that is the honest answer.
 */
final class BoughtTogetherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);

        // The seeded recommendations must not be able to hold this test up: the point is that
        // the strip now comes from receipts.
        DB::table('product_recommendations')->delete();
    }

    public function test_a_part_is_paired_with_what_was_actually_bought_beside_it(): void
    {
        [$part, $often, $once, $unrelated] = Product::query()->visible()->take(4)->get()->all();

        // Two orders pair $part with $often, one pairs it with $once.
        $this->order([$part, $often]);
        $this->order([$part, $often]);
        $this->order([$part, $once]);
        // And an order that does not involve $part at all.
        $this->order([$unrelated, $once]);

        $ids = $this->copurchases()->execute($part->id, 5);

        // Ranked by how many receipts each shares — that is the whole claim the heading makes.
        $this->assertSame([$often->id, $once->id], $ids);
    }

    public function test_a_part_is_never_its_own_companion(): void
    {
        [$part, $other] = Product::query()->visible()->take(2)->get()->all();

        $this->order([$part, $other]);

        /*
         | Without the != on product_id in the self-join, the top companion is ALWAYS the part
         | itself, on every product page — it shares every receipt with itself.
        */
        $this->assertNotContains($part->id, $this->copurchases()->execute($part->id, 5));
    }

    public function test_a_cancelled_order_is_not_a_purchase(): void
    {
        [$part, $other] = Product::query()->visible()->take(2)->get()->all();

        $this->order([$part, $other], ReceiptStatus::Cancelled);

        // Same rule the best-sellers query applies, and made in Ordering rather than by whoever
        // happens to read the table.
        $this->assertSame([], $this->copurchases()->execute($part->id, 5));
    }

    public function test_at_most_five_companions_come_back(): void
    {
        $products = Product::query()->visible()->take(8)->get();
        $part = $products->first();

        foreach ($products->skip(1) as $companion) {
            $this->order([$part, $companion]);
        }

        // He asked for the top five, or fewer when there are fewer.
        $this->assertCount(5, $this->copurchases()->execute($part->id, 5));
        $this->assertCount(7, $this->copurchases()->execute($part->id, 50));
    }

    public function test_the_order_is_stable_between_requests(): void
    {
        $products = Product::query()->visible()->take(4)->get();
        $part = $products->first();

        // Every pair shares exactly one receipt, so they all tie on count.
        foreach ($products->skip(1) as $companion) {
            $this->order([$part, $companion]);
        }

        $first = $this->copurchases()->execute($part->id, 5);

        /*
         | Ties need a tiebreaker or MySQL may order them differently per query, and the strip
         | would reshuffle itself on every reload. Same class of bug as the paginated list that
         | showed 24 of the same 25 SKUs on both pages.
        */
        $this->assertSame($first, $this->copurchases()->execute($part->id, 5));
    }

    public function test_a_companion_whose_product_was_deleted_is_skipped(): void
    {
        [$part, $gone] = Product::query()->visible()->take(2)->get()->all();

        $this->order([$part, $gone]);

        // receipt_lines.product_id is nullable and ON DELETE SET NULL: the line survives to
        // explain the receipt, but there is no product left to recommend.
        DB::table('receipt_lines')->where('product_id', $gone->id)->update(['product_id' => null]);

        $this->assertSame([], $this->copurchases()->execute($part->id, 5));
    }

    public function test_the_strip_shows_the_companions_and_totals_them(): void
    {
        [$part, $companion] = Product::query()->visible()->take(2)->get()->all();
        $part->update(['published_at' => now()]);

        $this->order([$part, $companion]);

        $html = $this->get("/product/{$part->slug}")->assertOk()->getContent();

        $this->assertStringContainsString('Frequently Bought Together', $html);
        // e(), not the raw name: Blade escapes, so a name with an apostrophe or an ampersand
        // never matches the raw string. This project has been caught by that before.
        $this->assertStringContainsString(e($companion->name), $html);

        // The bundle's checkboxes are the part plus its companions, and the total is their sum —
        // the theme shipped a hardcoded $409.27 here.
        preg_match_all('/data-bundle-price="(\d+)"/', $html, $prices);

        $this->assertCount(2, $prices[1]);

        preg_match('/data-bundle-total>([^<]+)</', $html, $total);

        $expected = array_sum(array_map('intval', $prices[1]));

        $this->assertSame(
            Money::fromMinor($expected)->format(),
            trim($total[1]),
            'The bundle total does not equal the sum of its own checkboxes.'
        );
    }

    public function test_each_bundle_checkbox_sits_inside_its_own_card(): void
    {
        [$part, $companion] = Product::query()->visible()->take(2)->get()->all();
        $part->update(['published_at' => now()]);

        $this->order([$part, $companion]);

        $html = $this->get("/product/{$part->slug}")->assertOk()->getContent();

        $bundle = substr($html, (int) strpos($html, 'check-box-product'));
        $bundle = substr($bundle, 0, (int) strpos($bundle, 'brator-product-single-frequently-total'));

        /*
         | THE STYLE FIX, pinned. The theme's CSS for this checkbox — the box, the tick, and the
         | "+" drawn between cards — is scoped to
         |   .check-box-product .…item-area.design-two .…item-checkbox
         | so a checkbox rendered OUTSIDE the card gets none of it. That is what the section
         | looked like: raw browser checkboxes with plain text labels, beside cards they did not
         | belong to, in a 2,175px-tall block.
         |
         | Asserted by position: every checkbox must appear after a card opens and before the
         | next one does.
        */
        $cards = preg_split('/(?=<div class="brator-product-single-item-area)/', $bundle);
        $cards = array_values(array_filter($cards, fn (string $chunk): bool => str_contains($chunk, 'item-area')));

        $this->assertCount(2, $cards, 'Expected one card for the part and one for its companion.');

        foreach ($cards as $index => $card) {
            $this->assertStringContainsString('brator-product-single-item-checkbox', $card,
                "Card {$index} has no checkbox inside it, so the theme's styling will not apply.");
            $this->assertStringContainsString('arow-check-right', $card,
                "Card {$index} is missing the element the theme draws the box and the + with.");
        }

        // And no stray checkbox outside a card, which is what the first version rendered.
        $outside = preg_replace('/<div class="brator-product-single-item-area.*/s', '', $bundle);

        $this->assertStringNotContainsString('type="checkbox"', (string) $outside,
            'There is a bundle checkbox sitting outside the cards.');
    }

    public function test_the_strip_is_hidden_when_nothing_was_ever_bought_with_the_part(): void
    {
        $part = Product::query()->visible()->firstOrFail();
        $part->update(['published_at' => now()]);

        $html = $this->get("/product/{$part->slug}")->assertOk()->getContent();

        /*
         | Sparse by nature — most parts have never shared a receipt with anything. Showing the
         | heading over the product on its own would both look broken and claim a pairing that
         | has never happened, which is exactly what the seeded table used to do.
        */
        $this->assertStringNotContainsString('Frequently Bought Together', $html);
    }

    public function test_the_seeded_recommendations_table_no_longer_drives_the_strip(): void
    {
        [$part, $invented] = Product::query()->visible()->take(2)->get()->all();
        $part->update(['published_at' => now()]);

        // A pairing nobody ever bought, recorded the way the seeder used to.
        DB::table('product_recommendations')->insert([
            'product_id' => $part->id,
            'related_product_id' => $invented->id,
            'type' => 'bought_together',
            'source' => 'computed',
            'score' => 100,
            'position' => 0,
        ]);

        $html = $this->get("/product/{$part->slug}")->assertOk()->getContent();

        // The strip is receipts or nothing. A seeded row must not put words in the shop's mouth.
        $this->assertStringNotContainsString('Frequently Bought Together', $html);
    }

    private function copurchases(): GetFrequentlyBoughtTogetherQuery
    {
        return app(GetFrequentlyBoughtTogetherQuery::class);
    }

    /**
     * A receipt holding these products, written the way checkout writes one.
     *
     * @param  list<Product>  $products
     */
    private function order(array $products, ReceiptStatus $status = ReceiptStatus::Paid): void
    {
        $receiptId = (string) Str::ulid();
        $now = now();

        DB::table('receipts')->insert([
            'id' => $receiptId,
            'receipt_number' => 'T-'.Str::upper(Str::random(8)),
            'status' => $status->value,
            'customer_name' => 'Test Buyer',
            'customer_email' => 'buyer@example.com',
            'shipping_address' => 'Somewhere 1',
            'subtotal_minor' => 0,
            'vat_minor' => 0,
            'shipping_minor' => 0,
            'total_minor' => 0,
            'discount_minor' => 0,
            'placed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($products as $product) {
            $price = ($product->sale_price_minor ?? $product->price_minor)->toPrimitive();

            DB::table('receipt_lines')->insert([
                'id' => (string) Str::ulid(),
                'receipt_id' => $receiptId,
                'product_id' => $product->id,
                'product_name_snapshot' => $product->name,
                'product_sku_snapshot' => $product->sku,
                'unit_price_minor' => $price,
                'quantity' => 1,
                'line_total_minor' => $price,
                'vat_rate' => 18,
                'vat_minor' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
