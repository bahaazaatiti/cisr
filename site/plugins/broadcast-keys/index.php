<?php

// Signing-identity generators (panel routes). Both mint an ECDSA P-256 key pair
// and hand the editor a PRIVATE key that is NEVER written to content/ (that would
// commit it to the public repo) — it lives only in the HTTP response, in memory.
// The PUBLIC key is safe to publish: viewers/visitors verify a stream or a chat
// line against it. The panel runs on the editor's own machine (static deploy), so
// generation is local, not exposed anywhere.
//
//   broadcast/genkey — one global identity → public key auto-saved onto the
//                       broadcast page, private streamed as a .txt download.
//   ticker/genkey    — live-news rows are a structure (many rooms), so there is
//                       no single field to auto-fill; instead we show the public
//                       key to paste into a row and offer the private key as a
//                       clean .txt download. JS/style-free so no panel-CSP snag.
//
// PEM in, PEM out. The browser side converts PEM → SPKI/PKCS8 for Web Crypto.

if (!function_exists('cisr_mint_ec_key')) {
    // EC P-256 (prime256v1) — matches the browser's ECDSA P-256. Returns
    // ['priv' => PEM, 'pub' => PEM] or null on any failure.
    function cisr_mint_ec_key(): ?array {
        if (!extension_loaded('openssl')) return null;
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ]);
        if ($res === false) return null;
        openssl_pkey_export($res, $privPem);          // PEM, in-memory only
        $details = openssl_pkey_get_details($res);
        $pubPem  = $details['key'] ?? '';
        if ($privPem === null || $pubPem === '') return null;
        return ['priv' => $privPem, 'pub' => trim($pubPem)];
    }
}

Kirby::plugin('site/broadcast-keys', [
    'areas' => [
        'broadcast' => fn () => [
            'menu'   => false,
            'views'  => [
                [
                    'pattern' => 'broadcast/genkey',
                    'action'  => function () {
                        $kirby = kirby();
                        // Auth: only a logged-in panel user may mint an identity.
                        if (!$kirby->user()) {
                            return \Kirby\Http\Response::redirect('/panel/login');
                        }
                        $kp = cisr_mint_ec_key();
                        if ($kp === null) {
                            return new \Kirby\Http\Response(
                                'Key generation failed (the openssl PHP extension is required).',
                                'text/plain',
                                500
                            );
                        }

                        // Persist ONLY the public key onto the broadcast page.
                        $page = $kirby->page('broadcast');
                        if ($page) {
                            $kirby->impersonate('kirby', function () use ($page, $kp) {
                                $page->update(['broadcast_pubkey' => $kp['pub']]);
                            });
                        }

                        // Stream the private key as a download. Never stored.
                        $stamp = date('Ymd-His');
                        return new \Kirby\Http\Response($kp['priv'], 'application/x-pem-file', 200, [
                            'Content-Disposition' => 'attachment; filename="cisr-broadcast-key-' . $stamp . '.txt"',
                            'Cache-Control'       => 'no-store',
                        ]);
                    },
                ],
                [
                    'pattern' => 'ticker/genkey',
                    'action'  => function () {
                        $kirby = kirby();
                        if (!$kirby->user()) {
                            return \Kirby\Http\Response::redirect('/panel/login');
                        }
                        $kp = cisr_mint_ec_key();
                        if ($kp === null) {
                            return new \Kirby\Http\Response(
                                'Key generation failed (the openssl PHP extension is required).',
                                'text/plain',
                                500
                            );
                        }

                        // No single field to auto-fill (rows are a structure), so
                        // hand both keys to the editor: public to copy into a row,
                        // private as a clean .txt download (loadable on the site).
                        // Plain HTML, no inline JS/CSS — bulletproof under any CSP.
                        $stamp = date('Ymd-His');
                        $pub   = htmlspecialchars($kp['pub'], ENT_QUOTES, 'UTF-8');
                        $href  = 'data:application/octet-stream;base64,' . base64_encode($kp['priv']);
                        $file  = 'cisr-livenews-key-' . $stamp . '.txt';
                        $html  = '<!doctype html><html><head><meta charset="utf-8">'
                            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
                            . '<title>Live-news identity</title></head><body>'
                            . '<h1>Live-news identity generated</h1>'
                            . '<p><b>1.</b> Copy the <b>public key</b> below and paste it into a '
                            . 'live-news row&rsquo;s &ldquo;Public key&rdquo; field in the panel, then Save.</p>'
                            . '<p><textarea readonly rows="6" style="width:100%;max-width:48rem;font-family:monospace">'
                            . $pub . '</textarea></p>'
                            . '<p><b>2.</b> <a download="' . $file . '" href="' . $href . '">'
                            . '&#x2913; Download the private key</a> &mdash; keep it safe. Load it on the '
                            . 'site (open COMMS &rarr; WHO&rsquo;S HERE) to write live news. It is never '
                            . 'stored on the server.</p>'
                            . '<p><a href="/panel/pages/news-ticker">&larr; Back to the ticker</a></p>'
                            . '</body></html>';
                        return new \Kirby\Http\Response($html, 'text/html', 200, [
                            'Cache-Control' => 'no-store',
                        ]);
                    },
                ],
            ],
        ],
    ],
]);
