<?php
declare(strict_types=1);

/**
 * Static-site builder for CISR.
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
//    (via cisr_build_stamp()) embeds it in every emitted HTML page.
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

// 3. Render.
$kirby = new Kirby\Cms\App();
$ssg   = new JR\StaticSiteGenerator($kirby);
$preserve = [
    '.nojekyll', '.kirbystatic', // dotfiles are auto-preserved; listed for clarity
    'CNAME',
    'README.md', 'MIRROR.md', 'MIRRORS.md',
    'sw.min.js',
    '_build.json',
];
$ssg->generate($outputFolder, '/', $preserve);
fwrite(STDERR, "rendered: {$outputFolder}\n");

// 4. Copy dissemination + runtime extras into the output root. Run after
//    generate() so a fresh wipe doesn't drop them. Idempotent.
$copies = [
    'sw.min.js',
    'MIRROR.md',
    'MIRRORS.md',
    'README.md',
    '_build.json',
    'CNAME', // optional — present only when a custom domain is set
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
