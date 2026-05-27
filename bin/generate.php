<?php
declare(strict_types=1);

/**
 * Static-site builder.
 *
 * Boots Kirby, writes a build stamp, runs JR\StaticSiteGenerator over all
 * pages × languages, then copies dissemination-layer extras (MIRROR.md,
 * sw.min.js, etc.) into the output. Invoked by .github/workflows/build.yml
 * on push to main and runnable locally for testing the deployable artifact.
 *
 * Usage: php bin/generate.php
 */

$root = dirname(__DIR__);
chdir($root);

require $root . '/kirby/bootstrap.php';

// 1. Build stamp — written BEFORE rendering so site/snippets/sidebar.php
//    (via build_stamp()) embeds it in every emitted HTML page.
$gitSha  = trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse --short HEAD 2>/dev/null')) ?: 'local';
$gitFull = trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null')) ?: '';
$stamp = [
    'sha'      => $gitSha,
    'sha_full' => $gitFull,
    'built_at' => gmdate('c'),
];
file_put_contents($root . '/_build.json', json_encode($stamp, JSON_PRETTY_PRINT) . "\n");
fwrite(STDERR, "stamp:    {$gitSha} @ {$stamp['built_at']}\n");

// 2. Output folder. Plugin refuses to wipe a pre-existing non-empty dir
//    unless an empty .kirbystatic marker lives in it — touch it to confirm
//    consent (the dir name 'static' is ours; nothing else writes here).
$outputFolder = $root . '/static';
if (!is_dir($outputFolder)) {
    mkdir($outputFolder, 0o755, true);
}
touch($outputFolder . '/.kirbystatic');

// 3. Render. BASE_URL env lets the Action pass the deploy prefix
//    ('/cisr/' for a project page, '/' for user/org pages or custom domains).
//    All Kirby url() / $page->url() / $site->url() output is rewritten to
//    use this prefix by the SSG (see _modifyBaseUrl in the plugin).
$baseUrl = (string) (getenv('BASE_URL') ?: '/');
if ($baseUrl !== '' && substr($baseUrl, -1) !== '/') $baseUrl .= '/';
fwrite(STDERR, "baseUrl:  {$baseUrl}\n");

$kirby = new Kirby\Cms\App();

// Page-only-when-rich: library-item / video / fraternal leaves earn a static
// page only if they have notes/summary. Otherwise the parent listing handles
// them as a row. Home is rendered separately by the SSG regardless.
$pages = $kirby->site()->index()->filter(function ($p) {
    $tpl = $p->intendedTemplate()->name();
    if (in_array($tpl, ['library-item', 'video', 'fraternal'], true)) {
        return $p->isRich();
    }
    return true;
});

$ssg = new JR\StaticSiteGenerator($kirby, null, $pages);
$preserve = [
    'CNAME',
    'README.md', 'MIRROR.md', 'MIRRORS.md',
    'sw.min.js',
    '_build.json',
    '_headers',
    'robots.txt',
];
$ssg->generate($outputFolder, $baseUrl, $preserve);
fwrite(STDERR, "rendered: {$outputFolder}\n");

// Library tree as a static asset, one per language. JS lazy-fetches this
// instead of embedding the JSON into every HTML page (would scale linearly
// with archive size). URL substitution mirrors what SSG does for HTML.
$basePrefix = rtrim($baseUrl, '/');
foreach ($kirby->languages() as $lang) {
    $kirby->setCurrentLanguage($lang->code());
    $libraryPage = $kirby->page('library');
    if (!$libraryPage) continue;
    $json = json_encode(tree_build($libraryPage), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $json = str_replace(['https://jr-ssg-base-url/', 'https://jr-ssg-base-url'], $basePrefix . '/', $json);
    $path = $lang->isDefault()
        ? $outputFolder . '/library.json'
        : $outputFolder . '/' . $lang->code() . '/library.json';
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0o755, true);
    file_put_contents($path, $json);
    fwrite(STDERR, "wrote:    " . str_replace($outputFolder . '/', '', $path) . "\n");
}

// sitemap.xml — one entry per emitted page × language, with hreflang alternates.
// Uses the same isRich-filtered $pages collection so the sitemap matches the deploy.
$kirby->setCurrentLanguage($kirby->defaultLanguage()->code());
$sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
$emitPage = function ($page) use (&$sitemap, $kirby, $basePrefix) {
    foreach ($kirby->languages() as $lang) {
        $kirby->setCurrentLanguage($lang->code());
        if (!$page->translation($lang->code())->exists()) continue;
        $loc = str_replace(['https://jr-ssg-base-url/', 'https://jr-ssg-base-url'], $basePrefix . '/', $page->url($lang->code()));
        $sitemap .= "  <url>\n    <loc>" . htmlspecialchars($loc) . "</loc>\n";
        foreach ($kirby->languages() as $alt) {
            if (!$page->translation($alt->code())->exists()) continue;
            $altLoc = str_replace(['https://jr-ssg-base-url/', 'https://jr-ssg-base-url'], $basePrefix . '/', $page->url($alt->code()));
            $sitemap .= '    <xhtml:link rel="alternate" hreflang="' . $alt->code() . '" href="' . htmlspecialchars($altLoc) . '"/>' . "\n";
        }
        $sitemap .= "  </url>\n";
    }
};
$emitPage($kirby->site()->homePage());
foreach ($pages->listed() as $p) $emitPage($p);
$sitemap .= '</urlset>' . "\n";
file_put_contents($outputFolder . '/sitemap.xml', $sitemap);
fwrite(STDERR, "wrote:    sitemap.xml\n");

// 4. Copy dissemination + runtime extras into the output root. Run after
//    generate() so a fresh wipe doesn't drop them. Idempotent.
$copies = [
    'sw.min.js',
    'MIRROR.md',
    'MIRRORS.md',
    'README.md',
    '_build.json',
    'CNAME',     // optional — present only when a custom domain is set
    '_headers',  // Netlify / CF Pages — ignored on gh-pages (meta CSP covers that)
    'robots.txt',
];
foreach ($copies as $name) {
    $src = $root . '/' . $name;
    if (file_exists($src)) {
        copy($src, $outputFolder . '/' . $name);
        fwrite(STDERR, "copied:   {$name}\n");
    }
}
// .nojekyll → tell GH Pages to skip Jekyll and serve files literally.
touch($outputFolder . '/.nojekyll');

fwrite(STDERR, "done\n");
