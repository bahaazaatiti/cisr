<?php

return [
    'debug' => false,
    'languages' => true,
    'cache' => [
        'pages' => [
            'active' => true,
            // Bypass the cache for logged-in panel users so editor-only UI
            // (e.g. status badges in site/snippets/ui/status-badge.php) renders
            // correctly. Without this the first anonymous visit caches the
            // no-badge HTML and serves it back to admins until cache expiry.
            // 'ignore' => function ($page) {
            //     return kirby()->user() !== null;
            // },
        ],
    ],
];
