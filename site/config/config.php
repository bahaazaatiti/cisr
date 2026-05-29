<?php

// Memoize tree_build per language via Kirby's pages cache. Local-dev / panel
// only — bin/generate.php emits static library.json straight from the tree.
// Kirby auto-purges the pages cache on content writes, so editors see fresh
// JSON immediately after saving.
$libraryJson = function (?string $lang = null): \Kirby\Http\Response {
    if ($lang) kirby()->setCurrentLanguage($lang);
    $body = kirby()->cache('pages')->getOrSet('library-tree-' . ($lang ?: 'default'), function () {
        $p = kirby()->page('library');
        return $p ? json_encode(tree_build($p), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null';
    }, 60);
    return new \Kirby\Http\Response($body, 'application/json');
};

return [
    'debug' => false,
    'languages' => true,
    'cache' => [
        'pages' => ['active' => true],
    ],
    // Panel:
    //  - vue compiler off (see https://getkirby.com/security/vue-compiler);
    //    panel-theme is CSS-only, nothing needs runtime template compilation.
    //  - menu adds quick-add buttons in the sidebar so editors don't have to
    //    drill Pages → … → +. Each entry opens Kirby's create dialog directly
    //    pre-targeted at the right parent + template. The literal items
    //    ('site', 'languages', etc.) keep Kirby's defaults in their usual slot.
    'panel' => [
        'vue' => ['compiler' => false],
        'menu' => [
            'site',
            'languages',
            'users',
            'system',
            '-',
            'quick-article' => [
                'icon'  => 'pen',
                'label' => 'New article',
                'link'  => 'dialogs/pages/create?parent=pages/articles&template=article',
            ],
            'quick-library' => [
                'icon'  => 'file',
                'label' => 'New library item',
                'link'  => 'dialogs/library/quick-add',
            ],
            'quick-fraternal' => [
                'icon'  => 'users',
                'label' => 'New fraternal',
                'link'  => 'dialogs/pages/create?parent=pages/fraternals&template=fraternal',
            ],
            'quick-video' => [
                'icon'  => 'video',
                'label' => 'New video',
                'link'  => 'dialogs/pages/create?parent=pages/videos&template=video',
            ],
        ],
    ],
    // Single knob: SKU badges read `option('brand.sku')`, sidebar head reads
    // `option('brand.site_id')`. Forkers change these two strings.
    'brand' => [
        'sku'     => 'CISR',
        'site_id' => 'LB-001',
    ],
    'routes' => [
        ['pattern' => 'library.json',         'action' => fn()      => $libraryJson()],
        ['pattern' => '(ar|fr)/library.json', 'action' => fn($lang) => $libraryJson($lang)],
    ],
];
