<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Exceptions;

use RuntimeException;

/**
 * A price moved while the item sat in someone's basket.
 *
 * Its own type rather than a bare RuntimeException because the caller must be able to
 * treat it differently: this is not a failure, it is a renegotiation. The shopper needs
 * to see the new price and choose, and the cart must be refreshed to the live prices
 * before they do — otherwise they hit the same wall on every retry.
 */
final class PriceChangedException extends RuntimeException {}
