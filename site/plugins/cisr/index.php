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
    ],
]);
