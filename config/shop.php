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

    /*
     | Delivery is a taxable supply in North Macedonia, so the delivery charge
     | carries VAT like any other line. Flip this only on an accountant's word.
     */
    'vat_on_delivery' => (bool) env('SHOP_VAT_ON_DELIVERY', true),

    /*
     | The shop's own contact details.
     |
     | These lived as the THEME's invented values, hardcoded in three separate places: a
     | phone number ("1800 500 1234 925"), an email (info@brator.com) and a US street
     | address in Asheville, North Carolina — all presented to shoppers as this shop's real
     | details, on every page, in a business based in North Macedonia.
     |
     | They are here so there is exactly one place to correct them, and so the footer, the
     | header and the contact page can never disagree. Set the real values in .env before
     | this goes anywhere near a customer.
     */
    'contact' => [
        'phone' => env('SHOP_PHONE'),
        'email' => env('SHOP_EMAIL', 'orders@brator.mk'),
        'address' => env('SHOP_ADDRESS'),
    ],
];
