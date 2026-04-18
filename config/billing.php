<?php

return [
    'default_currency' => env('BILLING_DEFAULT_CURRENCY', 'IDR'),
    'base_currency' => env('BILLING_BASE_CURRENCY', 'IDR'),

    'invoice_number' => [
        'prefix' => env('BILLING_INVOICE_PREFIX', 'INV'),
        'format' => env('BILLING_INVOICE_FORMAT', '%s-%d-%04d'),
    ],
];
