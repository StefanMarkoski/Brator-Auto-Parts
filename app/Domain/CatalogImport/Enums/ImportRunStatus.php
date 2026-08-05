<?php

declare(strict_types=1);

namespace App\Domain\CatalogImport\Enums;

enum ImportRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
