<?php

return [
    'debug' => false,
    'languages' => true,
    'cache' => [
        'pages' => ['active' => true],
    ],
    // Single knob: SKU badges read `option('brand.sku')`, sidebar head reads
    // `option('brand.site_id')`. Forkers change these two strings.
    'brand' => [
        'sku'     => 'CISR',
        'site_id' => 'LB-001',
    ],
];
