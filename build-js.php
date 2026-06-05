<?php
// Minify JS via JShrink (tedivm/jshrink) — a battle-tested state-machine
// minifier, replacing the old bespoke regex chain. Pure PHP, keeps the
// "no Node for the build" constraint. Usage: php build-js.php <in.js> <out.min.js>
declare(strict_types=1);

use JShrink\Minifier;

// Standalone CLI script — pull in Composer's autoloader for the JShrink class.
require __DIR__ . '/vendor/autoload.php';

if ($argc < 3) {
    fwrite(STDERR, "usage: php build-js.php <input.js> <output.min.js>\n");
    exit(1);
}
[$_, $in, $out] = $argv;
$src = file_get_contents($in);
if ($src === false) { fwrite(STDERR, "read error: $in\n"); exit(1); }

// flaggedComments=false: our own source carries no /*! … */ license banners,
// so drop every comment (the vendored *.min.js bundles aren't processed here).
// minify() re-throws on a parse error rather than returning silently — fail loud.
try {
    $min = Minifier::minify($src, ['flaggedComments' => false]);
} catch (\Throwable $e) {
    fwrite(STDERR, "minify error: $in — {$e->getMessage()}\n");
    exit(1);
}

file_put_contents($out, trim($min) . "\n");
$raw = filesize($out);
$gz  = strlen(gzencode(file_get_contents($out), 9));
fprintf(STDERR, "built: %s — %d bytes raw, %d bytes gz\n", $out, $raw, $gz);
