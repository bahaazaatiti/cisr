<?php

Kirby::plugin('cisr/helpers', [
    'pageMethods' => [],
]);

if (!function_exists('isPartialRequest')) {
    function isPartialRequest(): bool {
        $r = kirby()->request();
        return get('partial') === '1'
            || $r->header('X-Partial') === '1'
            || $r->header('HX-Request') === 'true';
    }
}
