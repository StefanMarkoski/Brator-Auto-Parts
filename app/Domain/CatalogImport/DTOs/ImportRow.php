<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\DTOs;

/**
 * One row of a supplier feed, after parsing and before anything is written.
 *
 * A DTO rather than a bare array because the row is handled in two separate passes — staged
 * on upload, applied later — and the shape has to survive the round trip through JSON in
 * import_staging_rows without anyone guessing at key names.
 */
final readonly class ImportRow
{
    /** The columns a feed may supply. Anything else in the file is ignored, not an error. */
    public const COLUMNS = [
        'sku', 'name', 'brand', 'category', 'price_net', 'sale_price',
        'stock', 'condition', 'short_description', 'part_number', 'fits',
    ];

    /** The ones without which a row cannot be a product. */
    public const REQUIRED = ['sku', 'name', 'price_net'];

    public function __construct(
        public string $sku,
        public ?string $name,
        public ?string $brand,
        public ?string $category,
        public ?string $priceNet,
        public ?string $salePrice,
        public ?string $stock,
        public ?string $condition,
        public ?string $shortDescription,
        public ?string $partNumber,
        /**
         * Which vehicles this part fits — engine codes or written-out names, separated by
         * semicolons. Optional: a feed that does not carry fitment still imports products,
         * they simply will not be findable by car.
         */
        public ?string $fits,
        /** 1-based line number in the uploaded file, for error messages a human can act on. */
        public int $line,
    ) {}

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values, int $line = 0): self
    {
        $get = static function (string $key) use ($values): ?string {
            $value = $values[$key] ?? null;

            if ($value === null) {
                return null;
            }

            $value = trim((string) $value);

            return $value === '' ? null : $value;
        };

        return new self(
            sku: (string) ($get('sku') ?? ''),
            name: $get('name'),
            brand: $get('brand'),
            category: $get('category'),
            priceNet: $get('price_net'),
            salePrice: $get('sale_price'),
            stock: $get('stock'),
            condition: $get('condition'),
            shortDescription: $get('short_description'),
            partNumber: $get('part_number'),
            fits: $get('fits'),
            line: (int) ($values['line'] ?? $line),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'brand' => $this->brand,
            'category' => $this->category,
            'price_net' => $this->priceNet,
            'sale_price' => $this->salePrice,
            'stock' => $this->stock,
            'condition' => $this->condition,
            'short_description' => $this->shortDescription,
            'part_number' => $this->partNumber,
            'fits' => $this->fits,
            'line' => $this->line,
        ];
    }

    /**
     * The vehicles this row claims to fit, one per entry.
     *
     * Semicolon-separated, because commas turn up inside vehicle names and a comma-separated
     * list inside a CSV cell is a quoting problem waiting to happen.
     *
     * @return list<string>
     */
    public function fitsReferences(): array
    {
        if ($this->fits === null) {
            return [];
        }

        $parts = array_map('trim', explode(';', $this->fits));

        return array_values(array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    /**
     * Why this row cannot be used, or null if it can.
     *
     * Returned as a sentence rather than a code: it is shown to whoever uploaded the file,
     * and "row 14: price_net is not a number" is actionable in a way that "E_INVALID" is not.
     */
    public function problem(): ?string
    {
        if ($this->sku === '') {
            return 'sku is empty — there is nothing to match this row against.';
        }

        if ($this->name === null) {
            return 'name is empty.';
        }

        if ($this->priceNet === null || ! is_numeric(str_replace(',', '.', $this->priceNet))) {
            return "price_net ('{$this->priceNet}') is not a number.";
        }

        if ((float) str_replace(',', '.', (string) $this->priceNet) < 0) {
            return 'price_net is negative.';
        }

        if ($this->salePrice !== null && ! is_numeric(str_replace(',', '.', $this->salePrice))) {
            return "sale_price ('{$this->salePrice}') is not a number.";
        }

        if ($this->salePrice !== null && $this->priceMinor() !== null
            && $this->salePriceMinor() > $this->priceMinor()) {
            return 'sale_price is higher than price_net.';
        }

        if ($this->stock !== null && ! ctype_digit($this->stock)) {
            return "stock ('{$this->stock}') is not a whole number.";
        }

        if ($this->condition !== null
            && ! in_array(strtolower($this->condition), ['new', 'refurbished', 'used'], true)) {
            return "condition ('{$this->condition}') must be new, refurbished or used.";
        }

        return null;
    }

    /** Minor units. Accepts a comma decimal separator, which European feeds use. */
    public function priceMinor(): ?int
    {
        return $this->priceNet === null
            ? null
            : (int) round(((float) str_replace(',', '.', $this->priceNet)) * 100);
    }

    public function salePriceMinor(): ?int
    {
        return $this->salePrice === null
            ? null
            : (int) round(((float) str_replace(',', '.', $this->salePrice)) * 100);
    }
}
