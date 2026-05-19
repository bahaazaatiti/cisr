<?php
// Minimal JS minifier — strips comments and collapses whitespace.
// Protects string literals (single/double/backtick) and /regex/ patterns
// from the comment stripper so they're not corrupted.
// Usage: php build-js.php <input.js> <output.min.js>
declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "usage: php build-js.php <input.js> <output.min.js>\n");
    exit(1);
}
[$_, $in, $out] = $argv;
$src = file_get_contents($in);
if ($src === false) { fwrite(STDERR, "read error: $in\n"); exit(1); }

// 1. Strip /* ... */ block comments FIRST (no comments inside our app.js strings).
$src = preg_replace('~/\*[\s\S]*?\*/~', '', $src);

// 2. Extract string + regex literals into placeholders so step 3 can't corrupt them.
//    Single/double-quoted strings are *single-line* in JS — exclude \n.
//    Regex literal heuristic: `/` allowed only after one of these prev tokens.
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

// 3. Strip // ... line comments (to end of line). Safe now: strings are placeholdered.
$src = preg_replace('~//[^\n]*~', '', $src);
// 4. Collapse whitespace.
$src = preg_replace('/[ \t]+/', ' ', $src);
$src = preg_replace('/\s*\n\s*/', "\n", $src);
$src = preg_replace('/\n+/', "\n", $src);
// 5. Strip whitespace around structural punctuation only (no arith ops — i++ +j gotcha).
$src = preg_replace('/\s*([{};,:()\[\]])\s*/', '$1', $src);
$src = preg_replace('/\s*=\s*/', '=', $src);
$src = preg_replace('/\s*<\s*/', '<', $src);
$src = preg_replace('/\s*>\s*/', '>', $src);
// 6. Restore tokens.
$src = preg_replace_callback('~\x01JSMIN(\d+)\x01~', fn($m) => $tokens[(int)$m[1]], $src);
// 7. Trim and write.
file_put_contents($out, trim($src) . "\n");
$raw = filesize($out);
$gz  = strlen(gzencode(file_get_contents($out), 9));
fprintf(STDERR, "built: %s — %d bytes raw, %d bytes gz\n", $out, $raw, $gz);
