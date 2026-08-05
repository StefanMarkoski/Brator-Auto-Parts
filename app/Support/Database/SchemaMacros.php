<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Schema\Blueprint;

/**
 * ULID columns, declared as narrowly as possible.
 *
 * Laravel's ulid()/foreignUlid() create char(26) in the table's default charset,
 * which is utf8mb4 — four bytes per character, so 104 bytes per value inside every
 * index that includes it. A ULID is Crockford base32: digits and uppercase letters
 * only, never a multibyte character.
 *
 * Declaring these columns `ascii` makes them 26 bytes instead of 104 — a 4x
 * reduction in the index footprint of every foreign key in the schema. That matters
 * most on product_vehicle_fitments and product_attribute_values, the two tables that
 * reach millions of rows and sit on the hottest read path. It recovers most of the
 * cost we accepted when we chose ULIDs over integer keys (schema plan §2).
 *
 * Collation is left at ascii_general_ci rather than a binary collation: the space win
 * is identical, and case-insensitive matching means a ULID typed or copied in the
 * wrong case still resolves instead of silently 404ing.
 */
final class SchemaMacros
{
    public static function register(): void
    {
        Blueprint::macro('ulidPrimary', function (string $column = 'id') {
            /** @var Blueprint $this */
            return $this->char($column, 26)->charset('ascii')->primary();
        });

        Blueprint::macro('ulidColumn', function (string $column) {
            /** @var Blueprint $this */
            return $this->char($column, 26)->charset('ascii');
        });
    }
}
