<?php

declare(strict_types=1);

namespace Tests\Feature;

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
        $product = Product::query()->firstOrFail();
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

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
