# Live endpoints — comms relays · ticker proxies · ticker nitters

The peer/live-news layer leans on **public, volunteer-run infrastructure that
comes and goes**. This is the one place to curate it without touching JS: edit a
section, rebuild, redeploy. Order matters — put the most reliable host first
(each list is tried top-down; the first that answers wins).

If this file is missing or a section is empty, the code falls back to
`option('comm.relays' | 'ticker.proxies' | 'ticker.nitters')` in
`site/config/config.php`, then to a hardcoded default baked into the JS — so a
fork that deletes this never breaks, it just loses the curation.

Format in every section: `- <url>` (one per line; blank lines and `<!-- … -->`
comments ignored). Liveness last checked **2026-05-31**.

## Relays

<!-- WebTorrent WSS trackers — comms (Trystero) signaling. Browsers can only use
     wss:// trackers. Two peers meet only via a tracker BOTH reach, so more live
     ones = faster, more reliable discovery. Trystero's own defaults rot:
     tracker.webtorrent.dev and tracker.files.fm were DOWN on 2026-05-31. -->
- wss://tracker.openwebtorrent.com
- wss://tracker.btorrent.xyz
- wss://tracker.openwebtorrent.com:443/announce
- wss://tracker.novage.com.ua

## Proxies

<!-- CORS proxies for the ticker — prepended to the target URL, which is then
     URL-encoded. Must return the body AND an Access-Control-Allow-Origin header.
     Empirically only allorigins reliably does both; the rest are best-effort. -->
- https://api.allorigins.win/raw?url=
- https://api.codetabs.com/v1/proxy/?quest=

## Nitters

<!-- Nitter instances for the X/Twitter lane (we read <instance>/<handle>/rss).
     Public nitter is flaky and instances die often — keep this fresh. -->
- https://nitter.net
- https://nitter.poast.org
- https://lightbrd.com
