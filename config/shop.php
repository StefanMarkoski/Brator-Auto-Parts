<?php

declare(strict_types=1);

return [
    /*
     | Prices are stored NET of VAT and VAT is added at checkout (decided 2026-08-05).
     | A single rate, so it lives here rather than as a column repeated across every
     | product row. The rate actually charged is snapshotted onto each receipt line,
     | because rates change by law and an old receipt must keep its own.
     */
    'vat_rate' => (float) env('SHOP_VAT_RATE', 18),

    'currency' => env('SHOP_CURRENCY', 'MKD'),

    // Single currency, but named here so a future multi-currency migration has one
    // place to start rather than a grep.
    'currency_symbol' => 'ден',
];
