<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\Enums;

enum ImportSourceType: string
{
    case Csv = 'csv';
    case Xml = 'xml';
    case Api = 'api';
}
