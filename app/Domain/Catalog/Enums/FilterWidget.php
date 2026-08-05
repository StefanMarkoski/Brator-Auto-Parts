<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * Selects which of the theme's EXISTING filter controls renders this attribute.
 * It never introduces new markup — that would be a styling change.
 */
enum FilterWidget: string
{
    case Checkbox = 'checkbox';
    case Range = 'range';
    case Swatch = 'swatch';
}
