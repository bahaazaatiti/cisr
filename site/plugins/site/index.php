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
            if (preg_match('/^\s*-\s*\[([^\]]+)\]\(([^)\s]+)\)/', $line, $m)) {
                $cache[] = ['name' => $m[1], 'url' => $m[2]];
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

if (!function_exists('file_kind')) {
    function file_kind(\Kirby\Cms\File $file): string {
        static $map = [
            'pdf'  => 'pdf',
            'jpg'  => 'img', 'jpeg' => 'img', 'png' => 'img', 'gif' => 'img', 'webp' => 'img', 'svg' => 'img',
            'mp4'  => 'mp4', 'webm' => 'mp4', 'mov' => 'mp4', 'ogg' => 'mp4',
            'mp3'  => 'snd', 'wav' => 'snd', 'flac' => 'snd', 'm4a' => 'snd',
            'doc'  => 'doc', 'docx' => 'doc', 'odt' => 'doc', 'rtf' => 'doc',
            'xls'  => 'xls', 'xlsx' => 'xls', 'csv' => 'xls', 'tsv' => 'xls', 'ods' => 'xls',
            'ppt'  => 'ppt', 'pptx' => 'ppt', 'odp' => 'ppt', 'key' => 'ppt',
            'zip'  => 'zip', 'tar' => 'zip', 'gz' => 'zip', '7z' => 'zip', 'rar' => 'zip',
            'txt'  => 'txt', 'md' => 'txt', 'log' => 'txt',
            'json' => 'dat', 'xml' => 'dat', 'yaml' => 'dat', 'yml' => 'dat',
        ];
        return $map[strtolower($file->extension())] ?? '...';
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
            if ($tpl === 'library-item') return $this->notes()->isNotEmpty();
            if ($tpl === 'video')        return $this->summary()->isNotEmpty();
            if ($tpl === 'fraternal')    return $this->notes()->isNotEmpty();
            return true;
        },
    ],
]);
