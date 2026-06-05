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

$config = [
    'debug' => false,
    'languages' => true,
    'cache' => [
        'pages' => ['active' => true],
    ],
    // thathoff/kirby-git-content (require-dev — Panel/edit-time only, never in the
    // static build). Editors commit + push content straight from /panel: auto-commit
    // + auto-push on every save, so an edit lands on the current branch and (on main)
    // trips the build.yml deploy. Branch UI off so editors stay on the branch they
    // opened. Auto-commit is scoped to the saved item (a `git add -- <path>`, not -A).
    'thathoff.git-content.commit' => true,
    'thathoff.git-content.push'   => true,
    'thathoff.git-content.disableBranchManagement' => true,
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
            // Page navigation — moved out of the Site view into the menu.
            'articles' => [
                'icon'  => 'pen',
                'label' => 'Articles',
                'link'  => 'pages/articles',
            ],
            'library' => [
                'icon'  => 'file',
                'label' => 'Library',
                'link'  => 'pages/library',
            ],
            'fraternals' => [
                'icon'  => 'users',
                'label' => 'Fraternals',
                'link'  => 'pages/fraternals',
            ],
            '-',
            // Quick-add buttons — open Kirby's create dialog pre-targeted.
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
            '-',
            // All-media overview — view + delete files across the whole site.
            'media' => [
                'icon'  => 'image',
                'label' => 'Media',
                'link'  => 'pages/media',
            ],
            '-',
            // git-content — "Publish": commit/push status, history + manual controls.
            'git-content' => [
                'icon'  => 'upload',
                'label' => 'Publish',
                'link'  => 'git-content',
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

// In a Codespace, the dev server sits behind an HTTPS proxy that forwards
// `Host: localhost`, so Kirby's URL auto-detection emits http://localhost:8765
// links — cross-origin to the public *.github.dev page, so the site's CSP ('self')
// blocks the CSS/JS/images and the Panel white-screens. Relative URLs are same-origin
// on whatever host serves the page. host() still resolves to localhost from the
// request so isLocal() stays true; panel.install is a belt-and-braces so first-user
// creation works even if the proxy host ever trips that check. Scoped to Codespaces,
// so local dev and the static build (no CODESPACE_NAME) are untouched.
if (getenv('CODESPACE_NAME')) {
    $config['url'] = '/';
    $config['panel']['install'] = true;
}

return $config;
