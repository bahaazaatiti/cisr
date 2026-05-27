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
    // Dev-mode JSON endpoint for the library tree. Static builds also emit a
    // copy at /library.json (and /<lang>/library.json) via bin/generate.php.
    'routes' => [
        [
            'pattern' => 'library.json',
            'action'  => function () {
                $p = kirby()->page('library');
                $json = $p ? json_encode(tree_build($p), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null';
                return new \Kirby\Http\Response($json, 'application/json');
            },
        ],
        [
            'pattern' => '(ar|fr)/library.json',
            'action'  => function ($lang) {
                kirby()->setCurrentLanguage($lang);
                $p = kirby()->page('library');
                $json = $p ? json_encode(tree_build($p), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null';
                return new \Kirby\Http\Response($json, 'application/json');
            },
        ],
    ],
];
