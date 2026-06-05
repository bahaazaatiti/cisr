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

// SITE_URL: absolute origin (scheme+host) for SEO/discovery tags only. CI
// derives it per-fork; unset locally. Internal links stay relative so the tree
// stays host-portable — only canonical/hreflang/og/sitemap get absolutized.
$siteUrl = rtrim((string) getenv('SITE_URL'), '/');
fwrite(STDERR, "siteUrl:  " . ($siteUrl !== '' ? $siteUrl : '(none — SEO URLs relative)') . "\n");

$kirby = new Kirby\Cms\App();

// Lean leaves render as rows in the parent listing instead of earning a page.
$pages = $kirby->site()->index()->filter(function ($p) {
    $tpl = $p->intendedTemplate()->name();
    // Media is the only panel-only page left (the file overview). Broadcast +
    // ticker config now live on the site model, so there's nothing else to skip.
    if ($tpl === 'media') return false;
    if (in_array($tpl, ['library-item', 'video', 'fraternal'], true)) {
        return $p->isRich();
    }
    return true;
});

$ssg = new JR\StaticSiteGenerator($kirby, null, $pages);
$preserve = [
    'CNAME',
    'README.md', 'MIRROR.md', 'MIRRORS.md', 'TRACKERS.md',
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
// Sitemap locs must be absolute. Use the CI origin when set, else fall back to
// the relative base (renders locally; just not crawl-submittable as-is).
$sitemapBase = $siteUrl !== '' ? $siteUrl . $baseUrl : $basePrefix . '/';
$sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
$emitPage = function ($page) use (&$sitemap, $kirby, $sitemapBase) {
    foreach ($kirby->languages() as $lang) {
        $kirby->setCurrentLanguage($lang->code());
        if (!$page->translation($lang->code())->exists()) continue;
        $loc = str_replace(['https://jr-ssg-base-url/', 'https://jr-ssg-base-url'], $sitemapBase, $page->url($lang->code()));
        $sitemap .= "  <url>\n    <loc>" . htmlspecialchars($loc) . "</loc>\n";
        foreach ($kirby->languages() as $alt) {
            if (!$page->translation($alt->code())->exists()) continue;
            $altLoc = str_replace(['https://jr-ssg-base-url/', 'https://jr-ssg-base-url'], $sitemapBase, $page->url($alt->code()));
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
    'TRACKERS.md',
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

// Absolute-ize the discovery tags. Lighthouse rejects relative canonical/
// hreflang; og:url + JSON-LD read better absolute too. Internal nav/asset links
// are deliberately left relative (the tree must mirror to any host). No-op
// locally (siteUrl empty) — SEO URLs only matter on a real deploy.
if ($siteUrl !== '') {
    $seoPatterns = [
        '~(<link rel="canonical" href=")(/[^"]*)"~',
        '~(<link rel="alternate" hreflang="[A-Za-z-]+" href=")(/[^"]*)"~',
        '~(<meta property="og:url" content=")(/[^"]*)"~',
        '~("(?:url|mainEntityOfPage)":")(/[^"]*)"~',   // JSON-LD (slashes unescaped)
    ];
    $seoCb = fn ($m) => $m[1] . $siteUrl . $m[2] . '"';
    $seoCount = 0;
    $hi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($outputFolder, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($hi as $hf) {
        $p = $hf->getPathname();
        if (!str_ends_with($p, '.html')) continue;
        file_put_contents($p, preg_replace_callback($seoPatterns, $seoCb, (string) file_get_contents($p)));
        $seoCount++;
    }
    fwrite(STDERR, "seo-abs:  {$seoCount} html files\n");
}

// Per-route gz size manifest. Lets CI surface byte-budget diffs vs prior
// build and gives clients a cheap way to read the page weight before navigating.
// The same pass records which minified JS bundles the HTML actually references,
// so the asset trim below can drop everything else.
$sizes = [];
$usedMin = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($outputFolder, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    $path = $f->getPathname();
    if (!str_ends_with($path, '.html')) continue;
    $html = (string) file_get_contents($path);
    $rel  = '/' . str_replace($outputFolder . '/', '', $path);
    $sizes[$rel] = strlen((string) gzencode($html, 9));
    if (preg_match_all('~/assets/js/([A-Za-z0-9_.-]+\.min\.js)~', $html, $m)) {
        foreach ($m[1] as $name) $usedMin[$name] = true;
    }
}
ksort($sizes);
file_put_contents($outputFolder . '/_sizes.json', json_encode($sizes, JSON_PRETTY_PRINT) . "\n");
fwrite(STDERR, "wrote:    _sizes.json (" . count($sizes) . " routes)\n");

// Trim deploy-only dead weight from static/assets/js (top level; vendor/ is a
// subfolder and is left alone): the unminified .js sources (build inputs — the
// deploy references only the .min builds) AND any *.min.js that no built page
// references (e.g. broadcast.min.js / ticker.min.js when their feature is off).
// Driven by the actual HTML refs above, so per-language differences are handled.
$jsDir = $outputFolder . '/assets/js';
if (is_dir($jsDir)) {
    $trimmed = 0;
    foreach (glob($jsDir . '/*.js') as $js) {
        $base = basename($js);
        $drop = str_ends_with($base, '.min.js')
            ? !isset($usedMin[$base])                       // minified bundle no page loads
            : file_exists(substr($js, 0, -3) . '.min.js');  // source with a .min twin
        if ($drop) { unlink($js); $trimmed++; }
    }
    fwrite(STDERR, "trimmed:  {$trimmed} unused js from static/assets/js\n");
}

fwrite(STDERR, "done\n");
