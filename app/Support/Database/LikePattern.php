<?php

declare(strict_types=1);

namespace App\Support\Database;

/**
 * Escapes user input for use inside a LIKE pattern.
 *
 * Without this, `%` typed into a search box is a wildcard rather than a character:
 * searching for "%" returned the entire 5,000-product catalogue, and "_" matched any
 * single character. Not a security hole — the value is still bound — but the query
 * stops meaning what the shopper asked, which for a search box is the whole job.
 *
 * The backslash must be escaped first, or escaping the others re-introduces it.
 */
final class LikePattern
{
    public static function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    /** "%term%" — matches anywhere. */
    public static function contains(string $value): string
    {
        return '%'.self::escape($value).'%';
    }

    /** "term%" — matches from the start, which can still use an index. */
    public static function startsWith(string $value): string
    {
        return self::escape($value).'%';
    }
}
