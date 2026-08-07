<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Product;
use App\Domain\CatalogImport\Actions\RunImportAction;
use App\Domain\CatalogImport\Enums\ImportRunStatus;
use App\Domain\CatalogImport\Models\ImportRun;
use App\Domain\CatalogImport\Models\ImportSource;
use App\Models\Enums\UserRole;
use App\Models\User;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\FitmentSeederSmall;
use Database\Seeders\ProductSeederSmall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

    public function test_the_fits_column_makes_an_imported_part_findable_by_car(): void
    {
        $this->seed(FitmentSeederSmall::class);

        $golf = $this->variantNamed('Volkswagen', 'Golf V', '1.9 TDI');

        $run = $this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock,fits
            XG-FIT-1,XGate Brake Disc Front,XGate,brake-discs,2450.00,24,Volkswagen Golf V 1.9 TDI
            CSV);

        $this->apply($run);

        $product = Product::query()->where('sku', 'XG-FIT-1')->firstOrFail();

        // The whole reason this column exists: without it an imported part is invisible the
        // moment a shopper picks their car, which on this shop is the main way in.
        $this->assertTrue(
            DB::table('product_vehicle_fitments')
                ->where('product_id', $product->id)
                ->where('vehicle_variant_id', $golf)
                ->exists(),
            'The fits column did not record any fitment.'
        );
    }

    public function test_an_engine_code_identifies_a_car_when_only_one_uses_it(): void
    {
        $this->seed(FitmentSeederSmall::class);

        $code = DB::table('vehicle_variants')
            ->select('engine_code')
            ->whereNotNull('engine_code')
            ->groupBy('engine_code')
            ->havingRaw('COUNT(*) = 1')
            ->value('engine_code');

        $this->assertNotNull($code, 'No unshared engine code to test with.');

        $expected = (int) DB::table('vehicle_variants')->where('engine_code', $code)->value('id');

        $run = $this->stage(<<<CSV
            sku,name,brand,category,price_net,stock,fits
            XG-FIT-2,XGate Oil Filter,XGate,oil-filters,410.00,85,{$code}
            CSV);

        $this->apply($run);

        // Engine codes are what a real supplier catalogue carries, so a feed has to be able to
        // use them rather than spelling out make and model.
        $product = Product::query()->where('sku', 'XG-FIT-2')->firstOrFail();

        $this->assertSame([$expected], DB::table('product_vehicle_fitments')
            ->where('product_id', $product->id)
            ->pluck('vehicle_variant_id')
            ->map(fn ($id): int => (int) $id)
            ->all());
    }

    public function test_an_engine_code_shared_by_two_models_matches_nothing_and_says_why(): void
    {
        $this->seed(FitmentSeederSmall::class);

        $shared = DB::table('vehicle_variants')
            ->select('engine_code')
            ->whereNotNull('engine_code')
            ->groupBy('engine_code')
            ->havingRaw('COUNT(*) > 1')
            ->value('engine_code');

        $this->assertNotNull($shared, 'No shared engine code in the seed to test with.');

        $run = $this->stage(<<<CSV
            sku,name,brand,category,price_net,stock,fits
            XG-FIT-3,XGate Brake Pad Set,XGate,brake-pads,1450.00,40,{$shared}
            CSV);

        $this->apply($run);

        $product = Product::query()->where('sku', 'XG-FIT-3')->firstOrFail();

        /*
         | Guessing would be the wrong kind of helpful. The same engine goes in several models,
         | and a brake pad for one is not a brake pad for another — so an ambiguous code records
         | nothing at all rather than quietly picking whichever row came back first.
        */
        $this->assertSame(0, DB::table('product_vehicle_fitments')
            ->where('product_id', $product->id)->count());

        $error = (string) $run->fresh()->stagingRows()->value('error');

        $this->assertStringContainsString($shared, $error);
        $this->assertStringContainsString('shared by more than one model', $error);
        // And the row still imported: fitment is supplementary, not a reason to reject a part.
        $this->assertNotNull($product->id);
    }

    public function test_an_unrecognised_vehicle_is_named_back_and_the_part_still_imports(): void
    {
        $this->seed(FitmentSeederSmall::class);

        $run = $this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock,fits
            XG-FIT-4,XGate Cabin Filter,XGate,cabin-filters,690.00,52,Tesla Cybertruck 4.0 EV
            CSV);

        $this->apply($run);

        $this->assertSame(1, Product::query()->where('sku', 'XG-FIT-4')->count(),
            'A typo in the fits column must not stop the part importing.');

        $error = (string) $run->fresh()->stagingRows()->value('error');

        // Named, not counted. "1 unrecognised vehicle" does not tell anybody which cell to fix.
        $this->assertStringContainsString('Tesla Cybertruck 4.0 EV', $error);
    }

    public function test_re_running_a_feed_does_not_duplicate_fitment(): void
    {
        $this->seed(FitmentSeederSmall::class);

        $csv = <<<'CSV'
            sku,name,brand,category,price_net,stock,fits
            XG-FIT-5,XGate Bulb H7,XGate,bulbs,290.00,150,Volkswagen Golf V 1.9 TDI
            CSV;

        $this->apply($this->stage($csv));
        $this->apply($this->stage($csv));

        $product = Product::query()->where('sku', 'XG-FIT-5')->firstOrFail();

        // A feed that is re-run daily must not grow the table every day, and must not fail on
        // a duplicate key and abandon the rest of the file.
        $this->assertSame(1, DB::table('product_vehicle_fitments')
            ->where('product_id', $product->id)->count());
    }

    public function test_fitment_is_added_not_replaced(): void
    {
        $this->seed(FitmentSeederSmall::class);

        $golf = $this->variantNamed('Volkswagen', 'Golf V', '1.9 TDI');
        $passat = $this->variantNamed('Volkswagen', 'Passat B6', '2.0 TDI');

        $this->apply($this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock,fits
            XG-FIT-6,XGate Brake Fluid,XGate,brake-fluid,320.00,120,Volkswagen Golf V 1.9 TDI
            CSV));

        $this->apply($this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock,fits
            XG-FIT-6,XGate Brake Fluid,XGate,brake-fluid,320.00,120,Volkswagen Passat B6 2.0 TDI
            CSV));

        $product = Product::query()->where('sku', 'XG-FIT-6')->firstOrFail();

        // The second feed did not know about the first. Syncing would have deleted the Golf,
        // the same way syncing categories would delete ones a human attached.
        $recorded = DB::table('product_vehicle_fitments')
            ->where('product_id', $product->id)
            ->pluck('vehicle_variant_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()->values()->all();

        $this->assertSame([min($golf, $passat), max($golf, $passat)], $recorded);
    }

    public function test_the_preview_reports_bad_vehicles_before_anything_is_written(): void
    {
        $this->seed(FitmentSeederSmall::class);

        $run = $this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock,fits
            XG-FIT-7,XGate Shock Absorber,XGate,shock-absorbers,4300.00,14,Tesla Cybertruck 4.0 EV
            CSV);

        // Counted before, not compared against zero: the seed has its own fitment, and what
        // matters is that the PREVIEW added none of its own.
        $before = DB::table('product_vehicle_fitments')->count();

        // The preview's job is to say what is wrong BEFORE somebody commits the file — a
        // problem only discovered on apply is a problem discovered too late.
        $preview = $this->actingAs($this->admin())
            ->get("/admin/imports/{$run->id}")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Tesla Cybertruck 4.0 EV', $preview);
        $this->assertSame($before, DB::table('product_vehicle_fitments')->count(),
            'The preview wrote fitment rows. A preview must write nothing at all.');
        $this->assertSame(0, Product::query()->where('sku', 'XG-FIT-7')->count());
    }

    public function test_the_shipped_xgate_feed_gives_every_part_fitment(): void
    {
        $this->seed(FitmentSeederSmall::class);

        $run = $this->stage(file_get_contents(base_path('database/fixtures/xgate-feed.csv')));

        $this->apply($run);

        /*
         | The fixture is what gets demonstrated, so it has to be right: every row names real
         | cars. A silent typo here would show as a part that vanishes the moment a car is
         | picked, which is the exact failure the fits column was added to fix.
        */
        $errors = $run->fresh()->stagingRows()
            ->whereNotNull('error')
            ->pluck('error')
            ->all();

        $this->assertSame([], $errors, "The shipped feed has fitment problems:\n".implode("\n", $errors));

        $withoutFitment = Product::query()
            ->where('sku', 'like', 'XG-%')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('product_vehicle_fitments')
                ->whereColumn('product_vehicle_fitments.product_id', 'products.id'))
            ->pluck('sku')
            ->all();

        $this->assertSame([], $withoutFitment,
            'These rows of the shipped feed got no fitment: '.implode(', ', $withoutFitment));
    }

    public function test_undoing_an_import_removes_what_it_created_and_frees_the_skus(): void
    {
        $this->seed(FitmentSeederSmall::class);

        $csv = <<<'CSV'
            sku,name,brand,category,price_net,stock,fits
            XG-U1,XGate Brake Disc,XGate,brake-discs,2450.00,24,Volkswagen Golf V 1.9 TDI
            XG-U2,XGate Brake Pad Set,XGate,brake-pads,1450.00,40,Volkswagen Golf V 1.9 TDI
            CSV;

        $run = $this->apply($this->stage($csv));

        $this->assertSame(2, Product::query()->where('sku', 'like', 'XG-U%')->count());

        $this->actingAs($this->admin())->delete("/admin/imports/{$run->id}")->assertRedirect();

        /*
         | HARD deleted, not trashed. The admin's own product delete is soft, so receipts keep
         | their link — but a soft delete leaves the SKU occupied, and re-importing the same file
         | would then fail on a duplicate SKU. That would make this button useless for the one
         | job it exists to do.
        */
        $this->assertSame(0, Product::withTrashed()->where('sku', 'like', 'XG-U%')->count());

        // Fitment went with them: the pivot is ON DELETE CASCADE.
        $this->assertSame(0, DB::table('product_vehicle_fitments as f')
            ->join('products as p', 'p.id', '=', 'f.product_id')
            ->where('p.sku', 'like', 'XG-U%')->count());

        // And the same file imports again from scratch, which is the whole point.
        $again = $this->apply($this->stage($csv));

        $this->assertSame(2, (int) $again->rows_created);
        $this->assertSame(2, Product::query()->where('sku', 'like', 'XG-U%')->count());
    }

    public function test_undoing_leaves_products_the_import_only_updated(): void
    {
        $existing = Product::query()->firstOrFail();

        $run = $this->apply($this->stage(<<<CSV
            sku,name,brand,category,price_net,stock
            {$existing->sku},Renamed By Feed,XGate,brake-discs,9999.00,7
            CSV));

        $this->actingAs($this->admin())->delete("/admin/imports/{$run->id}")->assertRedirect();

        /*
         | The product existed before the feed ran, and the importer does not record the values it
         | overwrote — so there is nothing to restore and deleting it would destroy something the
         | import never created. An undo that removed it would be worse than no undo at all.
        */
        $this->assertSame(1, Product::query()->where('sku', $existing->sku)->count());

        $this->assertStringContainsString('updated a product that already existed',
            (string) session('status'));
    }

    public function test_an_import_cannot_be_undone_twice(): void
    {
        $run = $this->apply($this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-U3,XGate Oil Filter,XGate,oil-filters,410.00,85
            CSV));

        $this->actingAs($this->admin())->delete("/admin/imports/{$run->id}")->assertRedirect();
        $this->actingAs($this->admin())->delete("/admin/imports/{$run->id}")->assertRedirect();

        // The second press must not try to delete rows that are already gone.
        $this->assertStringContainsString('already undone', (string) session('error'));
    }

    public function test_an_import_that_was_never_applied_cannot_be_undone(): void
    {
        $run = $this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-U4,XGate Bulb,XGate,bulbs,290.00,150
            CSV);

        $this->actingAs($this->admin())->delete("/admin/imports/{$run->id}")->assertRedirect();

        $this->assertStringContainsString('never applied', (string) session('error'));
        $this->assertNull($run->fresh()->reverted_at);
    }

    public function test_only_the_most_recent_import_can_be_undone(): void
    {
        $first = $this->apply($this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-U5,XGate Cabin Filter,XGate,cabin-filters,690.00,52
            CSV));

        $second = $this->apply($this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-U6,XGate Shock Absorber,XGate,shock-absorbers,4300.00,14
            CSV));

        /*
         | Undo has to run backwards. A later feed may have changed the very products an earlier
         | one created — a new price, new stock — and removing them would silently throw that away.
        */
        $this->actingAs($this->admin())->delete("/admin/imports/{$first->id}")->assertRedirect();

        $this->assertStringContainsString('Undo the most recent import first', (string) session('error'));
        $this->assertSame(1, Product::query()->where('sku', 'XG-U5')->count());

        // Once the newer one is undone, the older one becomes available — so a chain of runs can
        // be unwound in order rather than being stuck.
        $this->actingAs($this->admin())->delete("/admin/imports/{$second->id}")->assertRedirect();
        $this->actingAs($this->admin())->delete("/admin/imports/{$first->id}")->assertRedirect();

        $this->assertSame(0, Product::withTrashed()->whereIn('sku', ['XG-U5', 'XG-U6'])->count());
    }

    public function test_undoing_keeps_a_receipt_able_to_explain_itself(): void
    {
        $run = $this->apply($this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-U7,XGate Brake Fluid,XGate,brake-fluid,320.00,120
            CSV));

        $product = Product::query()->where('sku', 'XG-U7')->firstOrFail();
        $product->update(['published_at' => now()]);

        Mail::fake();

        $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 2]);
        $this->post('/checkout', [
            'customer_name' => 'Test Buyer',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '+389 70 123456',
            'shipping_address' => "ul. Partizanska 12\nSkopje",
        ])->assertRedirect();

        $line = DB::table('receipt_lines')->where('product_sku_snapshot', 'XG-U7')->first();
        $this->assertNotNull($line, 'The order did not produce a receipt line.');

        $this->actingAs($this->admin())->delete("/admin/imports/{$run->id}")->assertRedirect();

        $after = DB::table('receipt_lines')->where('id', $line->id)->first();

        /*
         | product_id is nullable and ON DELETE SET NULL by design, because a receipt line keeps
         | its OWN copy of the name, SKU, price, quantity and VAT — that is what makes a receipt
         | sealed. So removing a sold product loses the link through to a product page and nothing
         | else: the money still adds up and the line still says what was bought.
        */
        $this->assertNull($after->product_id);
        $this->assertSame($line->product_name_snapshot, $after->product_name_snapshot);
        $this->assertSame($line->line_total_minor, $after->line_total_minor);
        $this->assertSame($line->vat_minor, $after->vat_minor);

        // And the receipt still opens rather than 500ing on a missing relation.
        $this->get('/receipt/'.$line->receipt_id)->assertOk()->assertSee('XGate Brake Fluid', false);
    }

    public function test_a_guest_cannot_undo_an_import(): void
    {
        $run = $this->apply($this->stage(<<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-U8,XGate Bulb H7,XGate,bulbs,290.00,150
            CSV));

        /*
         | Logged out explicitly. The helper that applies the run authenticates as an admin, and
         | actingAs persists for the rest of the test — so without this the "guest" request would
         | still be the admin, and the test would pass while proving nothing.
        */
        Auth::logout();

        $this->delete("/admin/imports/{$run->id}")->assertRedirect('/admin/login');

        $this->assertSame(1, Product::query()->where('sku', 'XG-U8')->count());
    }

    private function variantNamed(string $make, string $model, string $variant): int
    {
        return (int) DB::table('vehicle_variants as v')
            ->join('vehicle_models as mo', 'mo.id', '=', 'v.model_id')
            ->join('vehicle_makes as mk', 'mk.id', '=', 'mo.make_id')
            ->where('mk.name', $make)->where('mo.name', $model)->where('v.name', $variant)
            ->value('v.id');
    }

    public function test_a_deleted_product_holding_the_sku_is_reported_not_crashed_into(): void
    {
        $csv = <<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-D1,XGate Brake Disc,XGate,brake-discs,2450.00,24
            CSV;

        $this->apply($this->stage($csv));

        // Deleted the way the admin deletes: soft, so the SKU stays occupied.
        $product = Product::query()->where('sku', 'XG-D1')->sole();
        $this->actingAs($this->admin())->delete("/admin/products/{$product->id}")->assertRedirect();

        $again = $this->apply($this->stage($csv));

        /*
         | THIS KILLED A LIVE DEMO. The SKU lookup includes trashed rows — it has to, the unique
         | index does too — and the update path then adjusted stock through findOrFail WITHOUT
         | withTrashed, so every row of the file died on "No query results for model [Product]
         | 01kz…", which tells nobody anything. Now it is a skip that says what to do about it.
        */
        $this->assertSame(0, (int) $again->rows_failed, 'A deleted SKU still fails the row.');
        $this->assertSame(1, (int) $again->rows_skipped);

        $error = (string) DB::table('import_staging_rows')
            ->where('import_run_id', $again->id)->value('error');

        $this->assertStringContainsString('belongs to a deleted product', $error);
        $this->assertStringContainsString('Restore it', $error);
    }

    public function test_erasing_a_supplier_removes_its_parts_runs_and_the_supplier_itself(): void
    {
        $csv = <<<'CSV'
            sku,name,brand,category,price_net,stock
            XG-P1,XGate Brake Disc,XGate,brake-discs,2450.00,24
            XG-P2,XGate Brake Pad Set,XGate,brake-pads,1450.00,40
            CSV;

        $this->apply($this->stage($csv));
        // A second run, undone — because that is the state a real screen gets into, and undoing
        // leaves staging rows pointing at products that no longer exist.
        $second = $this->apply($this->stage($csv));
        $this->actingAs($this->admin())->delete("/admin/imports/{$second->id}")->assertRedirect();

        $source = ImportSource::query()->where('name', 'XGate')->sole();

        $this->actingAs($this->admin())
            ->delete(route('admin.imports.sources.purge', $source->id))
            ->assertRedirect(route('admin.imports.index'))
            ->assertSessionHas('status', fn (string $s): bool => str_contains($s, 'XGate is gone'));

        // All of it, and hard: an occupied SKU is what made re-importing fail in the first place.
        $this->assertSame(0, Product::withTrashed()->where('sku', 'like', 'XG-P%')->count());
        $this->assertSame(0, ImportSource::query()->where('name', 'XGate')->count());
        $this->assertSame(0, ImportRun::query()->count());
        $this->assertSame(0, Brand::query()->where('name', 'XGate')->count());

        // And the same file imports cleanly again, into a shop that has never heard of them.
        $again = $this->apply($this->stage($csv));

        $this->assertSame(2, (int) $again->rows_created);
    }

    public function test_erasing_a_supplier_keeps_products_it_only_updated(): void
    {
        $existing = Product::query()->firstOrFail();
        $before = $existing->name;

        $this->apply($this->stage(<<<CSV
            sku,name,price_net
            {$existing->sku},XGate Renamed This One,999.00
            CSV));

        $source = ImportSource::query()->where('name', 'XGate')->sole();

        $this->actingAs($this->admin())
            ->delete(route('admin.imports.sources.purge', $source->id))
            // Named and explained, because a purge that leaves rows behind has to say which.
            ->assertSessionHas('status', fn (string $s): bool => str_contains($s, 'only UPDATED'));

        /*
         | Kept, and this is the line between the two controls: the importer does not snapshot the
         | values it overwrote, so there is nothing to put back — and deleting a part that existed
         | before this supplier ever sent a file would destroy catalogue that was never theirs.
        */
        $survivor = Product::query()->find($existing->id);

        $this->assertNotNull($survivor, 'A product the feed only updated was deleted.');
        $this->assertNotSame($before, $survivor->name);
    }

    public function test_a_guest_cannot_erase_a_supplier(): void
    {
        $this->apply($this->stage(<<<'CSV'
            sku,name,brand,price_net
            XG-G1,XGate Brake Disc,XGate,2450.00
            CSV));

        $source = ImportSource::query()->where('name', 'XGate')->sole();

        // Logged out explicitly: actingAs persists for the rest of the test, so without this the
        // "guest" would still be the admin and this would pass while proving nothing.
        Auth::logout();

        $this->delete(route('admin.imports.sources.purge', $source->id))->assertRedirect('/admin/login');

        $this->assertSame(1, ImportSource::query()->where('name', 'XGate')->count());
        $this->assertSame(1, Product::query()->where('sku', 'XG-G1')->count());
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
