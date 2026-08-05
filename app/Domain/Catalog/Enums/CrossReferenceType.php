<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum CrossReferenceType: string
{
    case Oem = 'oem';
    case Manufacturer = 'manufacturer';
    case Competitor = 'competitor';
    case Ean = 'ean';
}
