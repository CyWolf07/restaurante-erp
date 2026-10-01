<?php

return [
    // Esta fase solo crea borradores locales. No contiene transmisión automática.
    'default_document_type' => env('FISCAL_DOCUMENT_TYPE', 'electronic_invoice'),
    'seller' => [
        'tax_id' => env('FISCAL_SELLER_TAX_ID', ''),
        'verification_digit' => env('FISCAL_SELLER_DV', ''),
        'city' => env('FISCAL_SELLER_CITY', ''),
    ],
];
