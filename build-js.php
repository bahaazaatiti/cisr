<?php
// Minimal JS minifier. Usage: php build-js.php <input.js> <output.min.js>
declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "usage: php build-js.php <input.js> <output.min.js>\n");
    exit(1);
}
[$_, $in, $out] = $argv;
$src = file_get_contents($in);
if ($src === false) { fwrite(STDERR, "read error: $in\n"); exit(1); }

// Strip /* ... */ block comments first (no nested */ in our sources).
$src = preg_replace('~/\*[\s\S]*?\*/~', '', $src);

// Extract string + regex literals into placeholders so the line-comment
// stripper can't corrupt them. Regex form: `/` only after these prev tokens.
$tokens = [];
$ph = "\x01JSMIN%d\x01";
$pattern = '~
    (
        "(?:\\\\.|[^"\\\\\n])*"
      | \'(?:\\\\.|[^\'\\\\\n])*\'
      | `(?:\\\\.|[^`\\\\])*`
      | (?<=[(,=:;\[!&|?{}\n\r\t ])/(?:\\\\.|\[[^\]\n]*\]|[^/\\\\\n])+/[gimsuy]*
    )
~xu';
$src = preg_replace_callback($pattern, function ($m) use (&$tokens, $ph) {
    $tokens[] = $m[0];
    return sprintf($ph, count($tokens) - 1);
}, $src);

$src = preg_replace('~//[^\n]*~', '', $src);
$src = preg_replace('/[ \t]+/', ' ', $src);
$src = preg_replace('/\s*\n\s*/', "\n", $src);
$src = preg_replace('/\n+/', "\n", $src);
// Strip whitespace around structural punctuation only — arith ops (i++ +j) stay safe.
$src = preg_replace('/\s*([{};,:()\[\]])\s*/', '$1', $src);
$src = preg_replace('/\s*=\s*/', '=', $src);
$src = preg_replace('/\s*<\s*/', '<', $src);
$src = preg_replace('/\s*>\s*/', '>', $src);

$src = preg_replace_callback('~\x01JSMIN(\d+)\x01~', fn($m) => $tokens[(int)$m[1]], $src);
file_put_contents($out, trim($src) . "\n");
$raw = filesize($out);
$gz  = strlen(gzencode(file_get_contents($out), 9));
fprintf(STDERR, "built: %s — %d bytes raw, %d bytes gz\n", $out, $raw, $gz);
