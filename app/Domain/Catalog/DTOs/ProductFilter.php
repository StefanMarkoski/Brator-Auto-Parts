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
        public int $perPage = 12,
        public bool $listView = false,
    ) {}

    public const SORTS = ['newest', 'price_asc', 'price_desc', 'rating', 'name'];

    /** The page sizes the theme's "Show item" control offers. */
    public const PER_PAGE_OPTIONS = [12, 24, 48];

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
            perPage: $values['perPage'] ?? 12,
            listView: $values['listView'] ?? false,
        );
    }

    public static function fromRequest(Request $request, ?int $vehicleVariantId = null): self
    {
        /** @var array<string, list<string>> $attributes */
        $attributes = [];

        foreach ((array) $request->query('attr', []) as $code => $values) {
            /*
             | is_scalar BEFORE strval, and that is not defensive habit.
             |
             | ?attr[a][b][c]=1 is a legal query string. strval() on the nested array raised
             | "Array to string conversion", which the handler promotes to a 500 — measured on
             | /shop/brake-pads. No shopper types that, but a crawler following a mangled link
             | does, and it filled the log with errors on an unauthenticated public URL.
            */
            $clean = array_values(array_filter(
                array_map('strval', array_filter((array) $values, 'is_scalar')),
                fn ($v) => $v !== '',
            ));

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
            perPage: in_array((int) $request->query('per_page'), self::PER_PAGE_OPTIONS, true)
                ? (int) $request->query('per_page')
                : 12,
            listView: $request->query('view') === 'list',
        );
    }

    /**
     * The same filter, on a different page.
     *
     * Exists so a listing can pull the page number back to the last real one once it knows
     * how many there are — which fromRequest() cannot do, because the total is not known
     * until the query has run.
     */
    public function withPage(int $page): self
    {
        return new self(
            categoryPath: $this->categoryPath,
            categorySlug: $this->categorySlug,
            brandSlugs: $this->brandSlugs,
            priceMinMinor: $this->priceMinMinor,
            priceMaxMinor: $this->priceMaxMinor,
            attributes: $this->attributes,
            minRating: $this->minRating,
            vehicleVariantId: $this->vehicleVariantId,
            searchTerm: $this->searchTerm,
            sort: $this->sort,
            page: max(1, $page),
            perPage: $this->perPage,
            listView: $this->listView,
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
            perPage: $this->perPage,
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
            'per_page' => $this->perPage === 12 ? null : $this->perPage,
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
