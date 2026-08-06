<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Models\Receipt;
use App\Models\Enums\UserRole;
use App\Models\User;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);
    }

    public function test_every_admin_page_requires_a_login(): void
    {
        $product = Product::query()->firstOrFail();

        foreach ([
            '/admin', '/admin/receipts', '/admin/products',
            "/admin/products/{$product->id}/edit", '/admin/categories',
            '/admin/brands', '/admin/imports',
        ] as $page) {
            // Asserted as a path, not route(): the redirect is deliberately relative
            // so it works behind any domain or proxy.
            $this->get($page)->assertRedirect('/admin/login');
        }
    }

    public function test_receipts_are_not_reachable_without_a_login(): void
    {
        // The whole reason the receipts list lives in the admin: these rows carry
        // customer names, emails and delivery addresses.
        $this->get('/admin/receipts')->assertRedirect('/admin/login');
        $this->get('/receipts')->assertNotFound();
    }

    public function test_a_staff_member_can_sign_in_and_out(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@brator.test',
            'password' => 'secret-password',
            'role' => UserRole::Admin,
        ]);

        $this->post('/admin/login', [
            'email' => 'staff@brator.test',
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/admin/logout')->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_bad_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'staff@brator.test', 'password' => 'secret-password']);

        $this->post('/admin/login', ['email' => 'staff@brator.test', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_dashboard_reports_real_figures(): void
    {
        $receipt = Receipt::factory()->create(['total_minor' => 123_456, 'vat_minor' => 18_000]);

        $html = $this->actingAs($this->admin())->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString($receipt->receipt_number, $html);
        $this->assertStringContainsString('1.234,56 ден', $html);
    }

    public function test_editing_a_product_claims_only_the_fields_that_changed(): void
    {
        // Pinned rather than "whatever came back first": 20% of seeded products carry a
        // sale price, so this test used to pass or fail depending on which one it drew.
        // A test that depends on the seed is a test you cannot trust.
        $product = Product::query()->firstOrFail();
        $product->update(['sale_price_minor' => null]);
        $product->refresh();
        $originalPrice = $product->price_minor->toMajor();

        $this->actingAs($this->admin())->put("/admin/products/{$product->id}", [
            'name' => 'Renamed By Staff',
            'brand_id' => $product->brand_id,
            'price_major' => number_format($originalPrice, 2, '.', ''),
            'sale_price_major' => null,
            'stock_status' => $product->stock_status->value,
            'condition' => $product->condition->value,
            'short_description' => $product->short_description,
            'is_active' => 1,
            // Sent unchanged: stock is on this form now, and it is ledgered rather than
            // written straight to the column, so a save that omits it is a save that
            // cannot say what the stock is.
            'stock_quantity' => $product->stock_quantity,
        ])->assertRedirect(route('admin.products.edit', $product->id));

        $claimed = DB::table('product_field_overrides')
            ->where('product_id', $product->id)->pluck('field_name')->all();

        // Only `name` moved, so only `name` is now owned by a human. Saving a form must
        // not silently freeze every other field against future imports.
        $this->assertSame(['name'], $claimed);
        $this->assertSame('Renamed By Staff', $product->fresh()->name);
    }

    public function test_a_claimed_field_can_be_released_back_to_the_importer(): void
    {
        $product = Product::query()->firstOrFail();

        DB::table('product_field_overrides')->insert([
            'product_id' => $product->id,
            'field_name' => 'price_minor',
            'overridden_by' => null,
            'overridden_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->delete("/admin/products/{$product->id}/override", ['field' => 'price_minor'])
            ->assertRedirect(route('admin.products.edit', $product->id));

        $this->assertSame(0, DB::table('product_field_overrides')
            ->where('product_id', $product->id)->count());
    }

    public function test_the_edit_screen_shows_which_fields_staff_own(): void
    {
        $product = Product::query()->firstOrFail();

        DB::table('product_field_overrides')->insert([
            'product_id' => $product->id,
            'field_name' => 'price_minor',
            'overridden_by' => null,
            'overridden_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get("/admin/products/{$product->id}/edit")
            ->assertOk()
            ->assertSee('price_minor', false)
            ->assertSee('Release', false);
    }

    public function test_product_prices_are_validated(): void
    {
        $product = Product::query()->firstOrFail();

        $this->actingAs($this->admin())->put("/admin/products/{$product->id}", [
            'name' => 'Still Named',
            'price_major' => 'not-a-number',
            'stock_status' => 'in_stock',
            'condition' => 'new',
        ])->assertSessionHasErrors('price_major');
    }

    public function test_staff_can_create_a_product_and_its_opening_stock_is_ledgered(): void
    {
        $category = Category::query()->whereDoesntHave('children')->firstOrFail();

        $this->actingAs($this->admin())->post('/admin/products', [
            'sku' => 'BRT-TEST-0001',
            'name' => 'Test Brake Disc',
            'price_major' => '1450.00',
            'stock_quantity' => 40,
            'stock_status' => 'in_stock',
            'condition' => 'new',
            'is_active' => 1,
            'published' => 1,
            'category_ids' => [$category->id],
        ])->assertRedirect();

        $product = Product::query()->where('sku', 'BRT-TEST-0001')->firstOrFail();

        $this->assertSame('test-brake-disc', $product->slug, 'The slug should be derived from the name.');
        $this->assertSame(145000, $product->price_minor->toPrimitive());
        $this->assertSame(40, $product->stock_quantity);
        $this->assertNotNull($product->published_at);
        $this->assertSame([$category->id], $product->categories()->pluck('categories.id')->all());

        // The point of the action: opening stock is a movement, not a number that appeared
        // from nowhere. Every other change to this column is ledgered, so the ledger has to
        // be able to explain the opening figure too.
        $movements = DB::table('stock_movements')->where('product_id', $product->id)->get();

        $this->assertCount(1, $movements);
        $this->assertSame(40, (int) $movements->first()->delta);
        $this->assertSame('stocktake', $movements->first()->reason);
    }

    public function test_a_created_product_is_visible_in_the_shop_straight_away(): void
    {
        $category = Category::query()->whereDoesntHave('children')->firstOrFail();

        $this->actingAs($this->admin())->post('/admin/products', [
            'sku' => 'BRT-TEST-0002',
            'name' => 'Visible Test Part',
            'price_major' => '999.00',
            'stock_quantity' => 5,
            'stock_status' => 'in_stock',
            'condition' => 'new',
            'is_active' => 1,
            'published' => 1,
            'category_ids' => [$category->id],
        ]);

        $product = Product::query()->where('sku', 'BRT-TEST-0002')->firstOrFail();

        // Creating it in the admin has to actually put it in the shop. Four disagreeing
        // definitions of "visible" once let a 404'd product go on selling; this asserts the
        // storefront agrees with the panel.
        $this->get("/product/{$product->slug}")->assertOk()->assertSee('Visible Test Part', false);
    }

    public function test_an_unpublished_product_stays_out_of_the_shop(): void
    {
        $this->actingAs($this->admin())->post('/admin/products', [
            'sku' => 'BRT-TEST-0003',
            'name' => 'Draft Part',
            'price_major' => '100.00',
            'stock_quantity' => 1,
            'stock_status' => 'in_stock',
            'condition' => 'new',
            'is_active' => 1,
            // Left unpublished on purpose.
        ]);

        $product = Product::query()->where('sku', 'BRT-TEST-0003')->firstOrFail();

        $this->assertNull($product->published_at);
        $this->get("/product/{$product->slug}")->assertNotFound();
    }

    public function test_deleting_a_product_hides_it_from_the_shop_but_keeps_receipt_history(): void
    {
        $product = Product::query()->firstOrFail();
        $slug = $product->slug;

        $this->actingAs($this->admin())
            ->delete("/admin/products/{$product->id}")
            ->assertRedirect(route('admin.products.index'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->get("/product/{$slug}")->assertNotFound();

        // Soft, not hard: receipt lines reference this id, and reporting joins on it.
        $this->assertDatabaseHas('products', ['id' => $product->id]);

        // And it can come back, which is the whole reason the deleted list exists.
        $this->actingAs($this->admin())
            ->post("/admin/products/{$product->id}/restore")
            ->assertRedirect(route('admin.products.edit', $product->id));

        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_a_deleted_product_still_opens_in_the_admin(): void
    {
        $product = Product::query()->firstOrFail();
        $product->delete();

        // Without withTrashed() on the edit query this 404s, and the only route back from
        // a mistaken delete would be a database client.
        $this->actingAs($this->admin())
            ->get("/admin/products/{$product->id}/edit")
            ->assertOk()
            ->assertSee('This product is deleted');
    }

    public function test_editing_stock_writes_a_movement_rather_than_the_column(): void
    {
        $product = Product::query()->firstOrFail();
        $before = (int) $product->stock_quantity;

        $this->actingAs($this->admin())->put("/admin/products/{$product->id}", [
            'name' => $product->name,
            'brand_id' => $product->brand_id,
            'price_major' => number_format($product->price_minor->toMajor(), 2, '.', ''),
            'stock_status' => $product->stock_status->value,
            'condition' => $product->condition->value,
            'is_active' => 1,
            'stock_quantity' => $before + 7,
        ])->assertRedirect();

        $product->refresh();

        $this->assertSame($before + 7, $product->stock_quantity);

        $movement = DB::table('stock_movements')
            ->where('product_id', $product->id)
            ->where('reason', 'manual_adjustment')
            ->first();

        $this->assertNotNull($movement, 'A stock change made in the admin must be ledgered.');
        // The delta, derived — staff type the counted figure, not the difference.
        $this->assertSame(7, (int) $movement->delta);
    }

    public function test_restocking_an_out_of_stock_product_makes_it_buyable_again(): void
    {
        $product = Product::query()->firstOrFail();
        $product->update(['stock_quantity' => 0, 'stock_status' => 'out_of_stock']);

        $this->actingAs($this->admin())->put("/admin/products/{$product->id}", [
            'name' => $product->name,
            'brand_id' => $product->brand_id,
            'price_major' => number_format($product->price_minor->toMajor(), 2, '.', ''),
            // Deliberately left as out_of_stock: the arriving stock is what should clear it.
            'stock_status' => 'out_of_stock',
            'condition' => $product->condition->value,
            'is_active' => 1,
            'stock_quantity' => 12,
        ]);

        $product->refresh();

        // The sale path only ever had to flip in one direction. Without the other half, a
        // part that has physically arrived stays unbuyable and nobody can see why.
        $this->assertTrue($product->stock_status->isBuyable(),
            'Restocking must clear out_of_stock, or the part stays unbuyable after it arrives.');
    }

    public function test_a_duplicate_sku_is_refused_with_a_message(): void
    {
        $existing = Product::query()->firstOrFail();

        $this->actingAs($this->admin())->post('/admin/products', [
            'sku' => $existing->sku,
            'name' => 'Clashing Part',
            'price_major' => '100.00',
            'stock_quantity' => 1,
            'stock_status' => 'in_stock',
            'condition' => 'new',
        ])->assertSessionHasErrors('sku');

        $this->assertSame(1, Product::withTrashed()->where('sku', $existing->sku)->count());
    }

    public function test_a_sale_price_above_the_list_price_is_refused(): void
    {
        $this->actingAs($this->admin())->post('/admin/products', [
            'sku' => 'BRT-TEST-0004',
            'name' => 'Backwards Sale',
            'price_major' => '100.00',
            'sale_price_major' => '150.00',
            'stock_quantity' => 1,
            'stock_status' => 'in_stock',
            'condition' => 'new',
        ])->assertSessionHasErrors('sale_price_major');
    }

    public function test_the_pre_paint_theme_script_does_not_touch_the_body(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin')->assertOk()->getContent();

        // Everything before </head> runs while document.body is still null, so any
        // reference to it there throws — and TailAdmin's version did, on every page load,
        // which also stopped the rest of that script from running.
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        // Comments stripped first — the comment explaining this very bug names
        // document.body, and matching it would make the test pass on prose.
        $code = (string) preg_replace('#/\*.*?\*/#s', '', $head);

        $this->assertStringNotContainsString('document.body', $code,
            'A script in <head> references document.body, which does not exist yet.');

        // The other half: dark mode must still be applied before first paint.
        $this->assertStringContainsString("documentElement.classList.toggle('dark'", $head);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
