<?php

// Signing-identity generators (panel routes). Both mint an ECDSA P-256 key pair
// and hand the editor a PRIVATE key that is NEVER written to content/ (that would
// commit it to the public repo) — it lives only in the HTTP response, in memory.
// The PUBLIC key is safe to publish: viewers/visitors verify a stream or a chat
// line against it. The panel runs on the editor's own machine (static deploy), so
// generation is local, not exposed anywhere.
//
//   broadcast/genkey — one global identity → public key auto-saved onto the site
//                       (Broadcast tab), private streamed as a .txt download.
//   ticker/genkey    — single live-news identity (twin of broadcast) → public key
//                       auto-saved onto the site `livenews_pubkey` field, private
//                       streamed as a .txt download.
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

                        // Persist ONLY the public key onto the site (Broadcast tab).
                        $kirby->impersonate('kirby', function () use ($kirby, $kp) {
                            $kirby->site()->update(
                                ['broadcast_pubkey' => $kp['pub']],
                                $kirby->defaultLanguage()?->code()
                            );
                        });

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

                        // Auto-save the public key onto the site `livenews_pubkey`
                        // field (single live-news identity — mirrors broadcast).
                        $kirby->impersonate('kirby', function () use ($kirby, $kp) {
                            $kirby->site()->update(
                                ['livenews_pubkey' => $kp['pub']],
                                $kirby->defaultLanguage()?->code()
                            );
                        });

                        // Stream the private key as a download. Never stored.
                        $stamp = date('Ymd-His');
                        return new \Kirby\Http\Response($kp['priv'], 'application/x-pem-file', 200, [
                            'Content-Disposition' => 'attachment; filename="cisr-livenews-key-' . $stamp . '.txt"',
                            'Cache-Control'       => 'no-store',
                        ]);
                    },
                ],
            ],
        ],
    ],
]);
