<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum ProductCondition: string
{
    case New = 'new';
    case Refurbished = 'refurbished';
    case Used = 'used';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Refurbished => 'Refurbished',
            self::Used => 'Used',
        };
    }
}
