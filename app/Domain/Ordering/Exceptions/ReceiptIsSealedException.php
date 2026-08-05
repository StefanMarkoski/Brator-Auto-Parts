<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Exceptions;

use RuntimeException;

/**
 * Thrown when something tries to rewrite or delete a financial record.
 *
 * Deliberately loud rather than a silent no-op: code that expects to be able to edit a
 * receipt is code with a wrong assumption, and it should fail where the assumption is,
 * not quietly somewhere later.
 */
final class ReceiptIsSealedException extends RuntimeException {}
