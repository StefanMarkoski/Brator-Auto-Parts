<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Asserts the structural decisions from the schema plan, so a future migration
 * cannot quietly undo them.
 */
final class SchemaShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_ulid_column_is_ascii_and_26_bytes(): void
    {
        $wide = DB::select("
            SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME, CHARACTER_OCTET_LENGTH AS bytes
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND DATA_TYPE = 'char'
              AND CHARACTER_OCTET_LENGTH <> 26
        ");

        $this->assertSame([], $wide,
            'A ULID column is not ascii. In utf8mb4 a char(26) costs 104 bytes inside '
            .'every index that includes it, four times what it needs. Use $table->ulidPrimary() '
            .'/ $table->ulidColumn() rather than ulid()/foreignUlid().');
    }

    public function test_fitment_primary_key_is_vehicle_first(): void
    {
        $key = DB::select("
            SELECT COLUMN_NAME, SEQ_IN_INDEX
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'product_vehicle_fitments'
              AND INDEX_NAME = 'PRIMARY'
            ORDER BY SEQ_IN_INDEX
        ");

        // Vehicle first is the entire point: InnoDB stores the table in primary-key
        // order, so "parts for my car" is a contiguous range scan. Product-first turns
        // the same query into a scan.
        $this->assertSame(
            ['vehicle_variant_id', 'product_id'],
            array_map(fn ($row) => $row->COLUMN_NAME, $key)
        );
    }

    public function test_the_two_hot_filter_indexes_exist_on_attribute_values(): void
    {
        $indexes = DB::select("
            SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_attribute_values'
        ");
        $names = array_map(fn ($row) => $row->INDEX_NAME, $indexes);

        $this->assertContains('pav_attribute_option_product_index', $names);
        $this->assertContains('pav_attribute_number_product_index', $names);
    }

    public function test_products_table_stays_narrow(): void
    {
        $columns = DB::select("
            SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
        ");

        // The house DDD spec flags >30 fields as a red flag, and this table is read
        // dozens of rows at a time on every listing page. If this fails, the new
        // column probably belongs in seo_meta, an attribute, or its own table.
        $this->assertLessThanOrEqual(24, count($columns),
            'products has grown. Before widening the hottest table in the schema, check '
            .'whether the column belongs in seo_meta, product_attribute_values, or a '
            .'collection.');
    }

    public function test_removed_columns_have_not_crept_back(): void
    {
        $columns = collect(DB::select("
            SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
        "))->map(fn ($row) => $row->COLUMN_NAME)->all();

        // Each of these was deliberately removed. oem_number duplicated
        // product_cross_references; is_featured duplicated product_collections;
        // category_id became a pivot; the meta_* fields moved to seo_meta.
        foreach (['oem_number', 'is_featured', 'category_id', 'meta_title', 'meta_description'] as $gone) {
            $this->assertNotContains($gone, $columns,
                "products.{$gone} was removed as bloat — see the schema plan §3 before re-adding it.");
        }
    }
}
