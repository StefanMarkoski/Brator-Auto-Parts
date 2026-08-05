<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\Enums;

enum StagingRowOutcome: string
{
    case Pending = 'pending';
    case Created = 'created';
    case Updated = 'updated';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
