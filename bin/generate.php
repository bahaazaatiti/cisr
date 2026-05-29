<?php
// Static build entry: stamp the build, run the SSG over isRich-filtered
// pages, then emit library.json / sitemap.xml and copy dissemination extras.
declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

require $root . '/kirby/bootstrap.php';

// Stamp before render so sidebar can embed it via build_stamp().
$gitSha  = trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse --short HEAD 2>/dev/null')) ?: 'local';
$gitFull = trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null')) ?: '';
$stamp = ['sha' => $gitSha, 'sha_full' => $gitFull, 'built_at' => gmdate('c')];
file_put_contents($root . '/_build.json', json_encode($stamp, JSON_PRETTY_PRINT) . "\n");
fwrite(STDERR, "stamp:    {$gitSha} @ {$stamp['built_at']}\n");

// SSG refuses to wipe a non-empty dir unless this marker exists.
$outputFolder = $root . '/static';
if (!is_dir($outputFolder)) mkdir($outputFolder, 0o755, true);
touch($outputFolder . '/.kirbystatic');

// BASE_URL: deploy prefix from CI (project page vs custom domain).
$baseUrl = (string) (getenv('BASE_URL') ?: '/');
if ($baseUrl !== '' && substr($baseUrl, -1) !== '/') $baseUrl .= '/';
fwrite(STDERR, "baseUrl:  {$baseUrl}\n");

$kirby = new Kirby\Cms\App();

// Lean leaves render as rows in the parent listing instead of earning a page.
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

// Library tree as a per-language static asset; JS lazy-fetches it instead
// of embedding the JSON in every HTML page.
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

// sitemap.xml — one <url> per page × language, with hreflang alternates.
// Uses the same isRich filter so the sitemap matches what was deployed.
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

// Copy mirror/runtime extras after generate() so the wipe doesn't drop them.
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
touch($outputFolder . '/.nojekyll');

// Per-route gz size manifest. Lets CI surface byte-budget diffs vs prior
// build and gives clients a cheap way to read the page weight before navigating.
$sizes = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($outputFolder, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    $path = $f->getPathname();
    if (!str_ends_with($path, '.html')) continue;
    $rel = '/' . str_replace($outputFolder . '/', '', $path);
    $sizes[$rel] = strlen((string) gzencode((string) file_get_contents($path), 9));
}
ksort($sizes);
file_put_contents($outputFolder . '/_sizes.json', json_encode($sizes, JSON_PRETTY_PRINT) . "\n");
fwrite(STDERR, "wrote:    _sizes.json (" . count($sizes) . " routes)\n");

fwrite(STDERR, "done\n");
