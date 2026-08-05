<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum AttributeType: string
{
    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';
    case Option = 'option';

    /** Numeric attributes filter by range; the rest filter by exact value. */
    public function isNumeric(): bool
    {
        return $this === self::Number;
    }
}
