<?php

declare(strict_types=1);

namespace App\Domain\Catalog\DTOs;

use Illuminate\Http\Request;

/**
 * Everything a shopper can narrow a listing by, in one immutable object.
 *
 * Built from the request in one place so the listing, the search results and the facet
 * counts all read the same filter — three interpretations of "what is selected" is how
 * a sidebar ends up disagreeing with the results next to it.
 */
final readonly class ProductFilter
{
    /**
     * @param  list<string>  $brandSlugs
     * @param  array<string, list<string>>  $attributes  attribute code => selected values
     */
    private function __construct(
        public ?string $categoryPath = null,
        public ?string $categorySlug = null,
        public array $brandSlugs = [],
        public ?int $priceMinMinor = null,
        public ?int $priceMaxMinor = null,
        public array $attributes = [],
        public ?int $minRating = null,
        public ?int $vehicleVariantId = null,
        public ?string $searchTerm = null,
        public string $sort = 'newest',
        public int $page = 1,
        public bool $listView = false,
    ) {}

    public const SORTS = ['newest', 'price_asc', 'price_desc', 'rating', 'name'];

    /**
     * Rebuild with named values. Used to lift one filter group when counting that
     * group's own facets.
     *
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            categoryPath: $values['categoryPath'] ?? null,
            categorySlug: $values['categorySlug'] ?? null,
            brandSlugs: $values['brandSlugs'] ?? [],
            priceMinMinor: $values['priceMinMinor'] ?? null,
            priceMaxMinor: $values['priceMaxMinor'] ?? null,
            attributes: $values['attributes'] ?? [],
            minRating: $values['minRating'] ?? null,
            vehicleVariantId: $values['vehicleVariantId'] ?? null,
            searchTerm: $values['searchTerm'] ?? null,
            sort: $values['sort'] ?? 'newest',
            page: $values['page'] ?? 1,
            listView: $values['listView'] ?? false,
        );
    }

    public static function fromRequest(Request $request, ?int $vehicleVariantId = null): self
    {
        /** @var array<string, list<string>> $attributes */
        $attributes = [];

        foreach ((array) $request->query('attr', []) as $code => $values) {
            $clean = array_values(array_filter(array_map('strval', (array) $values), fn ($v) => $v !== ''));

            if (is_string($code) && $clean !== []) {
                $attributes[$code] = $clean;
            }
        }

        $sort = (string) $request->query('sort', 'newest');

        return new self(
            brandSlugs: array_values(array_filter((array) $request->query('brand', []))),
            // Prices arrive in major units from the theme's slider; store minor.
            priceMinMinor: self::minor($request->query('price_min')),
            priceMaxMinor: self::minor($request->query('price_max')),
            attributes: $attributes,
            minRating: self::intOrNull($request->query('rating')),
            vehicleVariantId: $vehicleVariantId,
            searchTerm: ($term = trim((string) $request->query('s', ''))) === '' ? null : $term,
            sort: in_array($sort, self::SORTS, true) ? $sort : 'newest',
            page: max(1, (int) $request->query('page', 1)),
            listView: $request->query('view') === 'list',
        );
    }

    public function forCategory(string $slug, string $path): self
    {
        return new self(
            categoryPath: $path,
            categorySlug: $slug,
            brandSlugs: $this->brandSlugs,
            priceMinMinor: $this->priceMinMinor,
            priceMaxMinor: $this->priceMaxMinor,
            attributes: $this->attributes,
            minRating: $this->minRating,
            vehicleVariantId: $this->vehicleVariantId,
            searchTerm: $this->searchTerm,
            sort: $this->sort,
            page: $this->page,
            listView: $this->listView,
        );
    }

    public function hasAnyNarrowing(): bool
    {
        return $this->brandSlugs !== []
            || $this->attributes !== []
            || $this->priceMinMinor !== null
            || $this->priceMaxMinor !== null
            || $this->minRating !== null
            || $this->vehicleVariantId !== null;
    }

    /** Is this option currently selected? Drives the checked state in the sidebar. */
    public function hasAttribute(string $code, string $value): bool
    {
        return in_array($value, $this->attributes[$code] ?? [], true);
    }

    public function hasBrand(string $slug): bool
    {
        return in_array($slug, $this->brandSlugs, true);
    }

    /**
     * The current filter as query parameters, so links and forms keep the selection.
     *
     * @return array<string, mixed>
     */
    public function toQuery(array $overrides = []): array
    {
        $query = array_filter([
            'brand' => $this->brandSlugs ?: null,
            'attr' => $this->attributes ?: null,
            'price_min' => $this->priceMinMinor === null ? null : $this->priceMinMinor / 100,
            'price_max' => $this->priceMaxMinor === null ? null : $this->priceMaxMinor / 100,
            'rating' => $this->minRating,
            's' => $this->searchTerm,
            'sort' => $this->sort === 'newest' ? null : $this->sort,
            'view' => $this->listView ? 'list' : null,
        ], fn ($value) => $value !== null);

        return [...$query, ...$overrides];
    }

    private static function minor(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) round(((float) $value) * 100);
    }

    private static function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
