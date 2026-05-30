<?php

if (!function_exists('yt_id')) {
    function yt_id(?string $url): ?string {
        if (!$url) return null;
        return preg_match('~(?:v=|/embed/|/shorts/|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m) ? $m[1] : null;
    }
}

if (!function_exists('magnet_parse')) {
    // Returns ['infohash' => '...', 'dn' => '...', 'trackers' => [...]] or null.
    function magnet_parse(?string $magnet): ?array {
        if (!$magnet || strncmp($magnet, 'magnet:?', 8) !== 0) return null;
        $hash = null; $dn = null; $trackers = [];
        foreach (explode('&', substr($magnet, 8)) as $p) {
            $eq = strpos($p, '=');
            if ($eq === false) continue;
            $k = substr($p, 0, $eq);
            $v = urldecode(substr($p, $eq + 1));
            if ($k === 'xt' && strncmp($v, 'urn:btih:', 9) === 0) $hash = strtolower(substr($v, 9));
            elseif ($k === 'dn') $dn = $v;
            elseif ($k === 'tr') $trackers[] = $v;
        }
        return $hash ? ['infohash' => $hash, 'dn' => $dn, 'trackers' => $trackers] : null;
    }
}

if (!function_exists('magnet_has_wss')) {
    // True iff the magnet declares at least one wss:// tracker. Browsers can
    // only reach WebSocket trackers — UDP/HTTP-only magnets are dead-on-arrival.
    // Used by isBroken() page method + the dashboard stats audit.
    function magnet_has_wss(?string $magnet): bool {
        $p = magnet_parse($magnet);
        if (!$p) return false;
        foreach ($p['trackers'] as $t) {
            if (stripos($t, 'wss://') === 0) return true;
        }
        return false;
    }
}

if (!function_exists('repo_root')) {
    function repo_root(): string {
        return realpath(__DIR__ . '/../../..') ?: dirname(__DIR__, 3);
    }
}

if (!function_exists('build_stamp')) {
    // Written by bin/generate.php before SSG renders; returns [] locally.
    function build_stamp(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        $f = repo_root() . '/_build.json';
        if (!file_exists($f)) return $cache = [];
        $j = json_decode((string) @file_get_contents($f), true);
        return $cache = (is_array($j) ? $j : []);
    }
}

if (!function_exists('mirrors_list')) {
    // First $limit `- [name](url) — note` entries from MIRRORS.md.
    function mirrors_list(int $limit = 3): array {
        static $cache = null;
        if ($cache !== null) return array_slice($cache, 0, $limit);
        $cache = [];
        $f = repo_root() . '/MIRRORS.md';
        if (!file_exists($f)) return [];
        foreach (file($f, FILE_IGNORE_NEW_LINES) as $line) {
            // `- [name](url) — note` — note is optional, accepts em/en/regular dash.
            if (preg_match('/^\s*-\s*\[([^\]]+)\]\(([^)\s]+)\)\s*(?:[—–-]\s*(.+))?$/u', $line, $m)) {
                $cache[] = ['name' => $m[1], 'url' => $m[2], 'note' => trim($m[3] ?? '')];
            }
        }
        return array_slice($cache, 0, $limit);
    }
}

if (!function_exists('tree_build')) {
    // Build the nested library tree the aside-right & client-side grid render.
    // Folders = nested `library` pages; files = `library-item` leaves with magnets.
    function tree_build(\Kirby\Cms\Page $node): array {
        $folders = [];
        $files   = [];
        foreach ($node->children()->listed()->sortBy('title', 'asc', SORT_NATURAL | SORT_FLAG_CASE) as $c) {
            $tpl = $c->intendedTemplate()->name();
            if ($tpl === 'library') {
                $folders[] = tree_build($c);
            } elseif ($tpl === 'library-item') {
                $files[] = [
                    'name'   => (string) ($c->title()->value() ?: $c->slug()),
                    'url'    => $c->isRich() ? (string) $c->url() : '',
                    'size'   => (string) ($c->size_human()->or('')),
                    'date'   => $c->added()->isNotEmpty() ? $c->added()->toDate('Y-m-d') : '',
                    'kind'   => ((string) $c->kind()) ?: 'other',
                    'magnet' => (string) $c->magnet(),
                ];
            }
        }
        return [
            'name'    => (string) ($node->title()->value() ?: $node->slug()),
            'slug'    => $node->slug(),
            'folders' => $folders,
            'files'   => $files,
        ];
    }
}

if (!function_exists('ticker_enabled')) {
    // The crawl is one global switch on the Home page (not translated) so the
    // toggle reads identically in every language. Off unless explicitly set —
    // a fresh fork stays quiet until an editor turns it on.
    function ticker_enabled(): bool {
        $home = page('home');
        return $home ? $home->ticker_on()->toBool() : false;
    }
}

if (!function_exists('ticker_feeds')) {
    // Remote crawl sources the visitor's browser fetches at view time — a kind
    // plus the editor's pasted link. No text lives here: the posts come from
    // the live fetch (see assets/js/ticker.js). Enabled, non-empty rows only.
    function ticker_feeds(): array {
        $tk = page('news-ticker');
        if (!$tk) return [];
        $out = [];
        foreach (['telegram' => 'tg', 'twitter' => 'x'] as $field => $kind) {
            foreach ($tk->$field()->toStructure() as $row) {
                if (!$row->enabled()->toBool()) continue;
                $url = trim((string) $row->url());
                if ($url !== '') $out[] = ['kind' => $kind, 'url' => $url];
            }
        }
        return $out;
    }
}

if (!function_exists('ticker_news')) {
    // The one hand-written lane: breaking-news lines typed by the editor.
    // Rendered server-side so they show instantly and survive a dead proxy.
    function ticker_news(): array {
        $tk = page('news-ticker');
        if (!$tk) return [];
        $out = [];
        foreach ($tk->breaking()->toStructure() as $row) {
            if (!$row->enabled()->toBool()) continue;
            $text = trim((string) $row->text());
            if ($text !== '') $out[] = ['text' => $text, 'url' => trim((string) $row->url())];
        }
        return $out;
    }
}

Kirby::plugin('site/helpers', [
    'pageMethods' => [
        'videoEmbedUrl' => function () {
            $id = yt_id((string) $this->youtube_url());
            return $id ? 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0' : null;
        },
        'youtubeThumbUrl' => function () {
            $id = yt_id((string) $this->youtube_url());
            return $id ? 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg' : null;
        },
        'fullTitle' => function () {
            return $this->isHomePage()
                ? (string) $this->site()->title()
                : $this->title() . ' · ' . $this->site()->title();
        },
        'metaDescription' => function () {
            $s = $this->summary();
            return ($s && $s->isNotEmpty()) ? (string) $s : (string) $this->site()->tagline();
        },
        'hasYouTubeSource' => function () {
            if ($this->intendedTemplate()->name() !== 'video') return false;
            if ((string) $this->source_type() === 'magnet') return false;
            return $this->youtube_url()->isNotEmpty();
        },
        'hasMagnetSource' => function () {
            $tpl = $this->intendedTemplate()->name();
            if ($tpl === 'video') {
                if ((string) $this->source_type() !== 'magnet') return false;
            } elseif ($tpl !== 'library-item') {
                return false;
            }
            return $this->magnet()->isNotEmpty();
        },
        'magnetUrl' => function () {
            $m = trim((string) $this->magnet());
            return $m !== '' ? $m : null;
        },
        'magnetParsed' => function () {
            return magnet_parse((string) $this->magnet());
        },
        'magnetKind' => function () {
            $tpl = $this->intendedTemplate()->name();
            if ($tpl === 'library-item') return ((string) $this->kind()) ?: 'other';
            if ($tpl === 'video')        return 'video';
            return 'other';
        },
        'magnetDisplayName' => function () {
            $p = magnet_parse((string) $this->magnet());
            return ($p && $p['dn']) ? $p['dn'] : (string) $this->title();
        },
        // True when a leaf earns its own static page. Lean leaves render as
        // plain rows in the parent listing and skip per-item generation.
        'isRich' => function () {
            $tpl = $this->intendedTemplate()->name();
            // Uniform contract across rich-able templates: a non-empty Notes
            // body earns a standalone static page. Summary stays the short
            // listing/meta blurb regardless.
            if (in_array($tpl, ['library-item', 'video', 'fraternal'], true)) {
                return $this->notes()->isNotEmpty();
            }
            return true;
        },
        // True when a magnet-bearing page (library-item or magnet-mode video)
        // has a magnet without any wss:// tracker — i.e. unreachable from a
        // browser. Drives the dashboard "broken magnets" stat.
        'isBroken' => function () {
            if (!$this->hasMagnetSource()) return false;
            return !magnet_has_wss((string) $this->magnet());
        },
    ],
    'siteMethods' => [
        // Count of library-items + videos whose magnet is unreachable from a
        // browser (no wss:// tracker). Cheap enough to call on every panel
        // dashboard render thanks to the page cache.
        'brokenMagnetCount' => function (): int {
            $n = 0;
            foreach ($this->index() as $p) {
                if ($p->isBroken()) $n++;
            }
            return $n;
        },
        'libraryItemCount' => function (): int {
            return $this->index()->filterBy('intendedTemplate', 'library-item')->count();
        },
        'fraternalCount' => function (): int {
            return $this->index()->filterBy('intendedTemplate', 'fraternal')->count();
        },
        'videoCount' => function (): int {
            return $this->index()->filterBy('intendedTemplate', 'video')->count();
        },
    ],
]);
