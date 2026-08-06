<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Product;
use App\Domain\CatalogImport\Actions\RunImportAction;
use App\Domain\CatalogImport\Enums\ImportRunStatus;
use App\Domain\CatalogImport\Models\ImportRun;
use App\Models\Enums\UserRole;
use App\Models\User;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The import runner.
 *
 * The rule it exists to enforce — an import never overwrites a field a human edited — was
 * built and enforced on the writing side months before anything could actually run an import.
 * Until these tests it was a promise nobody had ever kept or broken.
 */
final class ImportRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogStructureSeeder::class, ProductSeederSmall::class]);
    }

    public function test_a_feed_creates_products_and_the_new_brand_appears_in_the_filter(): void
    {
        $this->assertSame(0, Brand::query()->where('slug', 'xgate')->count());

        $run = $this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-1,XGate Brake Disc Front,XGate,brake-discs,2450.00,24
            XG-2,XGate Brake Pad Set Front,XGate,brake-pads,1450.00,40
            CSV);

        $this->apply($run);

        // The whole point of the "seller appears on its own" behaviour: nobody created XGate.
        $brand = Brand::query()->where('slug', 'xgate')->firstOrFail();
        $this->assertSame('XGate', $brand->name);

        $this->assertSame(2, Product::query()->where('brand_id', $brand->id)->count());

        // And the storefront's brand filter offers it, with the right count.
        $html = $this->get(route('search', ['brand' => ['xgate']]))->assertOk()->getContent();

        $this->assertStringContainsString('XGate', $html);
    }

    public function test_imported_products_arrive_unpublished(): void
    {
        $run = $this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-1,XGate Brake Disc Front,XGate,brake-discs,2450.00,24
            CSV);

        $this->apply($run);

        $product = Product::query()->where('sku', 'XG-1')->firstOrFail();

        /*
         | A feed can add hundreds of rows in one click. Publishing them straight away puts a
         | supplier's typo in front of shoppers before anyone has read it, so publishing stays
         | a person's decision.
        */
        $this->assertNull($product->published_at);
        $this->get("/product/{$product->slug}")->assertNotFound();
    }

    public function test_an_import_never_overwrites_a_field_a_human_edited(): void
    {
        $product = Product::query()->firstOrFail();
        $product->update(['name' => 'Name A Human Chose', 'price_minor' => 111_100]);

        // Exactly what the product editor records when somebody saves a changed field.
        DB::table('product_field_overrides')->insert([
            ['product_id' => $product->id, 'field_name' => 'name', 'overridden_by' => null, 'overridden_at' => now()],
            ['product_id' => $product->id, 'field_name' => 'price_minor', 'overridden_by' => null, 'overridden_at' => now()],
        ]);

        $run = $this->stage(<<<CSV
            sku,name,brand,price_net,stock,short_description
            {$product->sku},Supplier Would Rename This,XGate,999.00,7,A description from the feed
            CSV);

        $this->apply($run);

        $product->refresh();

        // The two claimed fields are untouched.
        $this->assertSame('Name A Human Chose', $product->name);
        $this->assertSame(111_100, $product->price_minor->toPrimitive());

        // And the unclaimed ones did update, or the importer would be useless.
        $this->assertSame('A description from the feed', $product->short_description);
        $this->assertSame(7, $product->stock_quantity);
    }

    public function test_the_row_says_which_fields_it_left_alone(): void
    {
        $product = Product::query()->firstOrFail();

        DB::table('product_field_overrides')->insert([
            'product_id' => $product->id, 'field_name' => 'price_minor',
            'overridden_by' => null, 'overridden_at' => now(),
        ]);

        $run = $this->stage(<<<CSV
            sku,name,price_net
            {$product->sku},Renamed By Feed,999.00
            CSV);

        $this->apply($run);

        $row = $run->stagingRows()->firstOrFail();

        // Silently refusing to write a field is indistinguishable from a broken importer.
        $this->assertStringContainsString('price_minor', (string) $row->error);
    }

    public function test_a_blank_cell_does_not_clear_an_existing_value(): void
    {
        $product = Product::query()->firstOrFail();
        $product->update(['short_description' => 'Copy somebody wrote by hand']);

        $run = $this->stage(<<<CSV
            sku,name,price_net,short_description
            {$product->sku},{$product->name},{$product->price_minor->toMajor()},
            CSV);

        $this->apply($run);

        // A blank cell is "no opinion", not "delete it".
        $this->assertSame('Copy somebody wrote by hand', $product->fresh()->short_description);
    }

    public function test_a_product_missing_from_the_feed_is_left_alone(): void
    {
        $absent = Product::query()->skip(1)->firstOrFail();
        $wasActive = $absent->is_active;

        $run = $this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-1,XGate Brake Disc Front,XGate,brake-discs,2450.00,24
            CSV);

        $this->apply($run);

        // Deactivating absentees is how a truncated file empties a shop.
        $this->assertSame($wasActive, $absent->fresh()->is_active);
        $this->assertNotSoftDeleted('products', ['id' => $absent->id]);
    }

    public function test_a_category_that_does_not_exist_is_refused_not_created(): void
    {
        $before = DB::table('categories')->count();

        $run = $this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-1,XGate Something,XGate,a-department-we-do-not-have,100.00,5
            CSV);

        $result = $this->apply($run);

        $this->assertSame(1, $result->rows_skipped);
        $this->assertSame(0, $result->rows_created);

        // A feed that could create departments would let a supplier rearrange the shop's
        // navigation.
        $this->assertSame($before, DB::table('categories')->count());
        $this->assertSame(0, Product::query()->where('sku', 'XG-1')->count());
    }

    public function test_bad_rows_are_skipped_with_a_reason_and_the_rest_still_import(): void
    {
        $run = $this->stage(<<<'CSV'
            sku,name,brand,category,price_net,sale_price,stock
            XG-1,XGate Good Row,XGate,brake-discs,2450.00,,24
            XG-2,XGate Bad Price,XGate,brake-discs,not-a-number,,10
            ,XGate No Sku,XGate,brake-discs,100.00,,10
            XG-4,,XGate,brake-discs,100.00,,10
            XG-5,XGate Sale Above List,XGate,brake-discs,100.00,150.00,10
            CSV);

        $result = $this->apply($run);

        // One bad row must not abandon the others.
        $this->assertSame(1, $result->rows_created, 'The good row should still have imported.');
        $this->assertSame(4, $result->rows_skipped);

        $errors = $run->stagingRows()->whereNotNull('error')->pluck('error')->implode(' | ');

        $this->assertStringContainsString('not a number', $errors);
        $this->assertStringContainsString('sku is empty', $errors);
        $this->assertStringContainsString('name is empty', $errors);
        $this->assertStringContainsString('higher than price_net', $errors);
    }

    public function test_the_preview_writes_nothing(): void
    {
        $csv = <<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-1,XGate Brake Disc Front,XGate,brake-discs,2450.00,24
            CSV;

        $run = $this->stage($csv);

        // Visiting the preview is a dry run. Nothing may exist afterwards.
        $this->actingAs($this->admin())->get("/admin/imports/{$run->id}")->assertOk();

        $this->assertSame(0, Product::query()->where('sku', 'XG-1')->count());
        $this->assertSame(0, Brand::query()->where('slug', 'xgate')->count());
        $this->assertSame(ImportRunStatus::Queued, $run->fresh()->status);
    }

    public function test_the_preview_counts_match_what_applying_actually_does(): void
    {
        $existing = Product::query()->firstOrFail();

        $run = $this->stage(<<<CSV
            sku,name,brand,category,price_net,stock
            XG-1,XGate New One,XGate,brake-discs,2450.00,24
            XG-2,XGate New Two,XGate,brake-pads,1450.00,40
            {$existing->sku},{$existing->name},XGate,brake-discs,999.00,5
            XG-4,XGate Bad,XGate,nope,100.00,5
            CSV);

        $preview = app(RunImportAction::class)
            ->execute($run, dryRun: true);

        $applied = $this->apply($run);

        // A preview that disagrees with the result is worse than no preview: it invites a
        // decision on numbers that turn out to be wrong.
        $this->assertSame($preview['created'], $applied->rows_created);
        $this->assertSame($preview['updated'], $applied->rows_updated);
        $this->assertSame($preview['skipped'], $applied->rows_skipped);
    }

    public function test_applying_the_same_run_twice_is_refused(): void
    {
        $run = $this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-1,XGate Brake Disc Front,XGate,brake-discs,2450.00,24
            CSV);

        $this->apply($run);

        $this->actingAs($this->admin())
            ->post("/admin/imports/{$run->id}/apply")
            ->assertSessionHas('error');

        // Re-running would re-stamp stock and log a second set of movements.
        $this->assertSame(1, Product::query()->where('sku', 'XG-1')->count());
    }

    public function test_re_running_a_feed_does_not_stack_duplicate_part_numbers(): void
    {
        $csv = <<<'CSV'
            sku,name,brand,category,price_net,stock,part_number
            XG-1,XGate Brake Disc Front,XGate,brake-discs,2450.00,24,569 004
            CSV;

        $this->apply($this->stage($csv));
        $this->apply($this->stage($csv));

        $product = Product::query()->where('sku', 'XG-1')->firstOrFail();

        $this->assertSame(1, DB::table('product_cross_references')
            ->where('product_id', $product->id)->count());
    }

    public function test_a_file_without_the_required_columns_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/imports', [
                'source_name' => 'XGate',
                'feed' => UploadedFile::fake()->createWithContent('feed.csv', "code,title\n1,Thing\n"),
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, ImportRun::query()->count());
    }

    public function test_a_guest_cannot_import_anything(): void
    {
        $this->post('/admin/imports', [
            'source_name' => 'XGate',
            'feed' => UploadedFile::fake()->createWithContent('feed.csv', "sku,name,price_net\nXG-1,Thing,10\n"),
        ])->assertRedirect('/admin/login');

        $this->assertSame(0, ImportRun::query()->count());
    }

    /** Uploads a CSV and returns the staged run. Heredoc indentation is stripped. */
    private function stage(string $csv): ImportRun
    {
        $body = implode("\n", array_map('trim', explode("\n", trim($csv))))."\n";

        $this->actingAs($this->admin())
            ->post('/admin/imports', [
                'source_name' => 'XGate',
                'feed' => UploadedFile::fake()->createWithContent('xgate.csv', $body),
            ])
            ->assertRedirect();

        return ImportRun::query()->latest('id')->firstOrFail();
    }

    private function apply(ImportRun $run): ImportRun
    {
        $this->actingAs($this->admin())->post("/admin/imports/{$run->id}/apply")->assertRedirect();

        return $run->fresh();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
