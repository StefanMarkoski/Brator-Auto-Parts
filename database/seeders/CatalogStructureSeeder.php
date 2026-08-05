<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Enums\AttributeType;
use App\Domain\Catalog\Enums\FilterWidget;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\AttributeOption;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The catalogue's shape: brands, a real category tree, and the attribute vocabulary
 * that drives the theme's filter sidebar.
 *
 * The attributes here are exactly the filters the theme's listing page ships —
 * Origins, Diameter, Width, Colour/Finish, Offset, Materials — plus a couple of
 * genuinely useful extras. Price, Brands and Ratings are not attributes; they are
 * columns on products.
 */
class CatalogStructureSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const TREE = [
        'Braking' => ['Brake Discs', 'Brake Pads', 'Brake Calipers', 'Brake Fluid'],
        'Wheels & Tires' => ['Alloy Wheels', 'Tires', 'Wheel Nuts', 'Hub Caps'],
        'Engine' => ['Timing Belts', 'Gaskets', 'Pistons', 'Turbochargers'],
        'Filters' => ['Oil Filters', 'Air Filters', 'Fuel Filters', 'Cabin Filters'],
        'Suspension' => ['Shock Absorbers', 'Springs', 'Control Arms', 'Bushings'],
        'Electrical' => ['Batteries', 'Alternators', 'Starters', 'Sensors'],
        'Lighting' => ['Headlights', 'Tail Lights', 'Bulbs', 'Fog Lights'],
        'Interior' => ['Floor Mats', 'Seat Covers', 'Steering Wheels', 'Mirrors'],
    ];

    /** @var array<string, array{label: string, type: AttributeType, widget: FilterWidget, unit: ?string, options: list<string>}> */
    private const ATTRIBUTES = [
        'origins' => [
            'label' => 'Origins', 'type' => AttributeType::Option, 'widget' => FilterWidget::Checkbox,
            'unit' => null, 'options' => ['OEM', 'Aftermarket', 'Genuine', 'Performance'],
        ],
        'materials' => [
            'label' => 'Materials', 'type' => AttributeType::Option, 'widget' => FilterWidget::Checkbox,
            'unit' => null, 'options' => ['Aluminium', 'Steel', 'Carbon Fibre', 'Ceramic', 'Rubber', 'Composite'],
        ],
        'color_finish' => [
            'label' => 'Color/Finish', 'type' => AttributeType::Option, 'widget' => FilterWidget::Swatch,
            'unit' => null, 'options' => ['Silver', 'Matte Black', 'Gloss Black', 'Gunmetal', 'Bronze', 'White'],
        ],
        'diameter' => [
            'label' => 'Diameter', 'type' => AttributeType::Number, 'widget' => FilterWidget::Range,
            'unit' => 'in', 'options' => [],
        ],
        'width' => [
            'label' => 'Width', 'type' => AttributeType::Number, 'widget' => FilterWidget::Range,
            'unit' => 'in', 'options' => [],
        ],
        'offset' => [
            'label' => 'Offset', 'type' => AttributeType::Number, 'widget' => FilterWidget::Range,
            'unit' => 'mm', 'options' => [],
        ],
    ];

    /** Swatch colours for the Colour/Finish filter, which the theme renders as chips. */
    private const SWATCHES = [
        'Silver' => '#C0C0C0', 'Matte Black' => '#28282B', 'Gloss Black' => '#0B0B0B',
        'Gunmetal' => '#2A3439', 'Bronze' => '#CD7F32', 'White' => '#F5F5F5',
    ];

    public function run(): void
    {
        Brand::factory()->count(40)->create();

        $position = 0;
        foreach (self::TREE as $parentName => $children) {
            $parent = Category::create([
                'name' => $parentName,
                'slug' => Str::slug($parentName),
                'description' => "Everything under {$parentName}.",
                'image_path' => sprintf('assets/images/categories/categories-%02d.png', ($position % 18) + 1),
                'path' => '/'.Str::slug($parentName).'/',
                'depth' => 0,
                'position' => $position++,
                'is_active' => true,
            ]);

            $childPosition = 0;
            foreach ($children as $childName) {
                Category::create([
                    'parent_id' => $parent->id,
                    'name' => $childName,
                    'slug' => Str::slug($childName),
                    'description' => "Shop {$childName} for your vehicle.",
                    'image_path' => sprintf('assets/images/categories/categories-%02d.png', ($childPosition % 18) + 1),
                    'path' => $parent->path.Str::slug($childName).'/',
                    'depth' => 1,
                    'position' => $childPosition++,
                    'is_active' => true,
                ]);
            }
        }

        $attributePosition = 0;
        $attributeIds = [];
        foreach (self::ATTRIBUTES as $code => $spec) {
            $attribute = Attribute::create([
                'code' => $code,
                'label' => $spec['label'],
                'type' => $spec['type'],
                'unit' => $spec['unit'],
                'is_filterable' => true,
                'filter_widget' => $spec['widget'],
                'position' => $attributePosition++,
            ]);
            $attributeIds[] = $attribute->id;

            foreach ($spec['options'] as $i => $value) {
                AttributeOption::create([
                    'attribute_id' => $attribute->id,
                    'value' => $value,
                    'swatch_hex' => self::SWATCHES[$value] ?? null,
                    'position' => $i,
                ]);
            }
        }

        // Every leaf category gets the filter set. Without category_attributes a
        // shopper browsing oil filters would be asked about wheel offset.
        $rows = [];
        foreach (Category::query()->where('depth', 1)->pluck('id') as $categoryId) {
            foreach ($attributeIds as $i => $attributeId) {
                $rows[] = [
                    'category_id' => $categoryId,
                    'attribute_id' => $attributeId,
                    'position' => $i,
                ];
            }
        }
        DB::table('category_attributes')->insert($rows);
    }
}
