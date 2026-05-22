<?php

if (!function_exists('isPartialRequest')) {
    function isPartialRequest(): bool {
        return get('partial') === '1' || kirby()->request()->header('X-Partial') === '1';
    }
}

if (!function_exists('cisr_youtube_id')) {
    function cisr_youtube_id(?string $url): ?string {
        if (!$url) return null;
        if (preg_match('~(?:v=|/embed/|/shorts/|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return $m[1];
        }
        return null;
    }
}

if (!function_exists('cisr_magnet_parse')) {
    /**
     * Parse a magnet URI. Returns null if it doesn't look like one.
     * Shape: ['infohash' => '...', 'dn' => '...', 'trackers' => [...]]
     */
    function cisr_magnet_parse(?string $magnet): ?array {
        if (!$magnet || strncmp($magnet, 'magnet:?', 8) !== 0) return null;
        $query = substr($magnet, 8);
        $parts = explode('&', $query);
        $hash = null; $dn = null; $trackers = [];
        foreach ($parts as $p) {
            $eq = strpos($p, '=');
            if ($eq === false) continue;
            $k = substr($p, 0, $eq);
            $v = urldecode(substr($p, $eq + 1));
            if ($k === 'xt' && strncmp($v, 'urn:btih:', 9) === 0) {
                $hash = strtolower(substr($v, 9));
            } elseif ($k === 'dn') {
                $dn = $v;
            } elseif ($k === 'tr') {
                $trackers[] = $v;
            }
        }
        if (!$hash) return null;
        return ['infohash' => $hash, 'dn' => $dn, 'trackers' => $trackers];
    }
}

if (!function_exists('cisr_magnet_has_wss')) {
    function cisr_magnet_has_wss(?string $magnet): bool {
        $p = cisr_magnet_parse($magnet);
        if (!$p) return false;
        foreach ($p['trackers'] as $t) {
            if (stripos($t, 'wss://') === 0) return true;
        }
        return false;
    }
}

if (!function_exists('cisr_file_kind')) {
    function cisr_file_kind(\Kirby\Cms\File $file): string {
        $ext = strtolower($file->extension());
        $map = [
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
        return $map[$ext] ?? '...';
    }
}

Kirby::plugin('cisr/helpers', [
    'pageMethods' => [
        'videoEmbedUrl' => function () {
            /** @var \Kirby\Cms\Page $this */
            $id = cisr_youtube_id((string) $this->youtube_url());
            return $id ? 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0' : null;
        },
        'youtubeThumbUrl' => function () {
            /** @var \Kirby\Cms\Page $this */
            $id = cisr_youtube_id((string) $this->youtube_url());
            return $id ? 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg' : null;
        },
        'fullTitle' => function () {
            /** @var \Kirby\Cms\Page $this */
            return $this->isHomePage()
                ? (string) $this->site()->title()
                : $this->title() . ' · ' . $this->site()->title();
        },
        'metaDescription' => function () {
            /** @var \Kirby\Cms\Page $this */
            $s = $this->summary();
            $v = ($s && $s->isNotEmpty()) ? (string) $s : (string) $this->site()->tagline();
            return $v;
        },
        'hasYouTubeSource' => function () {
            /** @var \Kirby\Cms\Page $this */
            if ($this->intendedTemplate()->name() !== 'video') return false;
            $type = (string) $this->source_type();
            if ($type === 'magnet') return false;
            return $this->youtube_url()->isNotEmpty();
        },
        'hasMagnetSource' => function () {
            /** @var \Kirby\Cms\Page $this */
            $tpl = $this->intendedTemplate()->name();
            if ($tpl === 'video') {
                if ((string) $this->source_type() !== 'magnet') return false;
            } elseif ($tpl !== 'library-item') {
                return false;
            }
            return $this->magnet()->isNotEmpty();
        },
        'magnetUrl' => function () {
            /** @var \Kirby\Cms\Page $this */
            $m = trim((string) $this->magnet());
            return $m !== '' ? $m : null;
        },
        'magnetParsed' => function () {
            /** @var \Kirby\Cms\Page $this */
            return cisr_magnet_parse((string) $this->magnet());
        },
        'magnetKind' => function () {
            /** @var \Kirby\Cms\Page $this */
            $tpl = $this->intendedTemplate()->name();
            if ($tpl === 'library-item') {
                $k = (string) $this->kind();
                return $k !== '' ? $k : 'other';
            }
            if ($tpl === 'video') return 'video';
            return 'other';
        },
        'magnetDisplayName' => function () {
            /** @var \Kirby\Cms\Page $this */
            $p = cisr_magnet_parse((string) $this->magnet());
            if ($p && $p['dn']) return $p['dn'];
            return (string) $this->title();
        },
    ],
]);
