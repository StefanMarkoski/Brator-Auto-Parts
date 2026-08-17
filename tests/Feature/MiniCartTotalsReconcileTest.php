<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Enums\StockStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Actions\GenerateCouponAction;
use App\Domain\Ordering\Queries\Internal\GetBasketSummaryQuery;
use App\Domain\Ordering\Services\BasketResolver;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The numbers in the header's mini-cart must add up to the total printed under them.
 *
 * They did not, on every page of the shop. The panel showed Subtotal and VAT and then a
 * total larger than both together, because delivery was inside the total with no row:
 * one air filter read 844,05 + 186,13 against a total of 1.220,18 — a 190,00 gap with
 * nothing on screen to explain it. The VAT figure includes delivery VAT, so it was not
 * 18% of the subtotal either. With a coupon the subtotal shown was the PRE-discount one
 * and the discount had no row at all, so the panel quoted more than was being charged.
 *
 * It reconciled only above the free-delivery threshold, which is exactly why it survived
 * being looked at: the first basket anyone tries in a demo is usually a big one.
 *
 * WHAT THIS ASSERTS: the rendered rows, parsed back out of the HTML, sum to the rendered
 * total. Not the DTO's arithmetic — that was always right, and testing it would have
 * passed against the broken page. The defect was a view omitting a row, so the view is
 * what gets read.
 */
final class MiniCartTotalsReconcileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);
    }

    private function buyable(int $priceMinor): Product
    {
        $product = Product::query()->where('stock_status', StockStatus::InStock)->firstOrFail();

        $product->update([
            'sale_price_minor' => null,
            'price_minor' => $priceMinor,
            'stock_quantity' => 99,
            'published_at' => now()->subDay(),
            'is_active' => true,
        ]);

        return $product->refresh();
    }

    /**
     * Every money row inside the mini-cart panel, as minor units, keyed by its label.
     *
     * @return array{rows: array<string, int>, total: int}
     */
    private function miniCartMoney(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();

        $panel = $this->sliceMiniCart($html);

        preg_match_all(
            '/<div class="brator-cart-item-money"><span>(.*?)<\/span><span>(.*?)<\/span><\/div>/s',
            $panel,
            $matches,
            PREG_SET_ORDER,
        );

        $rows = [];

        foreach ($matches as $m) {
            $rows[trim(strip_tags($m[1]))] = $this->toMinor($m[2]);
        }

        preg_match('/<div class="brator-cart-total-header"><span>total<\/span><span>(.*?)<\/span>/s', $panel, $t);

        $this->assertNotEmpty($t, 'The mini-cart total row was not found — every assertion here would be vacuous.');

        return ['rows' => $rows, 'total' => $this->toMinor($t[1])];
    }

    /** The first mini-cart panel on the page, so the cart page's own summary cannot bleed in. */
    private function sliceMiniCart(string $html): string
    {
        $start = strpos($html, 'brator-cart-item-list-money-area');
        $this->assertNotFalse($start, 'No mini-cart money area on the page.');

        $end = strpos($html, 'brator-cart-total-action', $start);

        return substr($html, $start, $end - $start);
    }

    /** "1.220,18 ден" and "Free" and "−90,00 ден" all become signed minor units. */
    private function toMinor(string $formatted): int
    {
        $text = trim(html_entity_decode(strip_tags($formatted)));

        if (str_contains($text, 'Free')) {
            return 0;
        }

        $negative = str_contains($text, '−') || str_contains($text, '-');
        $digits = preg_replace('/[^0-9,]/u', '', $text) ?? '';
        $minor = (int) round((float) str_replace(',', '.', str_replace('.', '', $digits)) * 100);

        return $negative ? -$minor : $minor;
    }

    private function assertRowsSumToTotal(string $url, string $because): void
    {
        ['rows' => $rows, 'total' => $total] = $this->miniCartMoney($url);

        $this->assertNotEmpty($rows, 'No money rows rendered in the mini-cart.');

        $this->assertSame(
            $total,
            array_sum($rows),
            $because.' — rows '.json_encode($rows).' do not sum to the total '.$total,
        );
    }

    public function test_the_rows_add_up_when_delivery_is_charged(): void
    {
        // Under the free-delivery threshold, so delivery is a real charge — the case that
        // was broken, and the one a casual check skips because the basket is small.
        $this->post('/cart/add', ['product_id' => $this->buyable(84_405)->id, 'quantity' => 1]);

        $this->assertRowsSumToTotal('/', 'Homepage mini-cart with delivery charged');
        $this->assertRowsSumToTotal('/shop/braking', 'Shop-header mini-cart with delivery charged');
    }

    public function test_the_rows_add_up_when_delivery_is_free(): void
    {
        // Over the threshold. This case reconciled even with the bug, which is how the
        // defect survived: delivery was zero, so the missing row hid nothing.
        $this->post('/cart/add', ['product_id' => $this->buyable(500_000)->id, 'quantity' => 3]);

        $this->assertRowsSumToTotal('/', 'Homepage mini-cart with free delivery');
    }

    public function test_the_rows_add_up_with_a_coupon_applied(): void
    {
        $product = $this->buyable(100_000);
        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 2]);

        // The small seeder makes no coupons, so make one rather than hope for one — a
        // fixture that depends on what happened to be seeded is how the last three flaky
        // tests in this suite started.
        $coupon = app(GenerateCouponAction::class)->execute(15);
        $this->post('/cart/coupon', ['code' => $coupon->code]);

        $this->assertRowsSumToTotal('/', 'Homepage mini-cart with a coupon applied');
    }

    public function test_delivery_is_a_row_of_its_own_and_not_hidden_in_the_total(): void
    {
        $this->post('/cart/add', ['product_id' => $this->buyable(84_405)->id, 'quantity' => 1]);

        ['rows' => $rows] = $this->miniCartMoney('/');

        // The specific omission that caused the gap.
        $this->assertArrayHasKey('Delivery', $rows);
        $this->assertGreaterThan(0, $rows['Delivery'], 'Delivery should be charged on a basket this size.');
    }

    public function test_the_discount_row_appears_only_while_a_code_is_discounting(): void
    {
        $product = $this->buyable(100_000);
        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 2]);

        ['rows' => $before] = $this->miniCartMoney('/');
        $this->assertArrayNotHasKey('Discount', $before, 'A discount row appeared with no coupon applied.');

        $coupon = app(GenerateCouponAction::class)->execute(15);
        $this->post('/cart/coupon', ['code' => $coupon->code]);

        ['rows' => $after] = $this->miniCartMoney('/');

        $discountRow = array_filter($after, fn (int $v, string $k): bool => str_starts_with($k, 'Discount'), ARRAY_FILTER_USE_BOTH);
        $this->assertCount(1, $discountRow, 'No discount row while a coupon is discounting.');
        $this->assertLessThan(0, reset($discountRow), 'The discount should be shown as money coming off.');
    }

    public function test_the_cart_page_names_the_base_its_vat_was_computed_on(): void
    {
        $product = $this->buyable(100_000);
        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 2]);

        $summary = app(GetBasketSummaryQuery::class)
            ->execute(app(BasketResolver::class)->current());

        // The label used to name the discounted goods while the figure beside it also
        // included delivery VAT, so it described two thirds of its own number.
        $this->get('/cart')->assertOk()->assertSee('On '.$summary->vatBase()->format(), false);
    }
}
