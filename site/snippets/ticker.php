<?php
  /** @var \Kirby\Cms\Site $site */
  // Live news crawl, pinned to the top of the band between the sidebars.
  // Hand-written "breaking" lines render here server-side (instant, and they
  // survive a dead proxy); Telegram/Twitter posts are fetched live in the
  // browser by assets/js/ticker.js and merged in. Rendered once in the
  // persistent shell (footer) so it rides across SPA navs. CSS-only marquee —
  // see .ticker in tailwind.src.css.
  if (!ticker_active()) return;
  $news  = ticker_news();
  $feeds = ticker_feeds();

  // Config the browser fetcher reads. The proxy + nitter fallback lists are
  // overridable via config.php so a fork can curate its own when a public host
  // dies — nothing site-specific is hardcoded in the JS.
  $cfg = [
    'feeds'   => $feeds,
    'max'     => 5,
    'cap'     => 160,
    'ttl'     => (int) option('ticker.ttl', 180),
    // Precedence: TRACKERS.md (curated, editable without touching code) →
    // config option → the JS-baked default (in ticker.js, if both are empty).
    'proxies' => array_values(trackers_list('proxies') ?: (array) option('ticker.proxies', [
      'https://api.allorigins.win/raw?url=',
      'https://api.codetabs.com/v1/proxy/?quest=',
    ])),
    'nitters' => array_values(trackers_list('nitters') ?: (array) option('ticker.nitters', [
      'https://nitter.net',
      'https://nitter.poast.org',
      'https://lightbrd.com',
    ])),
  ];

  // Initial speed sized to the hand-written lines; ticker.js recomputes once
  // the fetched posts merge in.
  $chars = 0;
  foreach ($news as $it) $chars += mb_strlen($it['text']) + 4;
  $dur = max(20, (int) round(($chars ?: 60) * 0.11));

  // Server-rendered fallback segment: the hand-written lines, each preceded by
  // a dot. ticker.js clones these as the always-present base layer.
  $seg = function () use ($news) {
    foreach ($news as $it):
      $link = (bool) preg_match('~^(https?://|/)~i', $it['url']);
      $ext  = $link && preg_match('~^https?://~i', $it['url']);
  ?>
    <?php if ($link): ?><a class="tk-item" href="<?= esc($it['url'], 'attr') ?>"<?= $ext ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?php else: ?><span class="tk-item"><?php endif ?><span class="tk-dot" aria-hidden="true"></span><span class="tk-text"><?= esc($it['text']) ?></span><?= $link ? '</a>' : '</span>' ?>
  <?php endforeach;
  };
?>
<?php /* role=marquee: a scrolling live-news region. Gives the aria-label a valid
         host (aria-label on a role-less div is prohibited) and tells AT this is a
         non-urgent live region, not silent decoration. */ ?>
<div class="ticker" data-ticker role="marquee" aria-label="<?= esc(t('ticker.region', 'Live news'), 'attr') ?>">
  <script type="application/json" class="tk-config"><?= json_encode($cfg, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <svg class="tk-defs" aria-hidden="true" focusable="false"><defs>
    <symbol id="tk-x" viewBox="0 0 24 24"><path fill="currentColor" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24h-6.66l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></symbol>
    <symbol id="tk-tg" viewBox="0 0 24 24"><path fill="currentColor" d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/></symbol>
  </defs></svg>
  <div class="tk-track" style="--ticker-dur:<?= $dur ?>s">
    <div class="tk-seg"><?php $seg() ?></div>
    <?php /* Duplicate is decoration for the seamless loop: inert keeps its links
             out of the tab order so keyboard users don't land on invisible copies
             (aria-hidden alone leaves them focusable — fails axe aria-hidden-focus). */ ?>
    <div class="tk-seg" aria-hidden="true" inert><?php $seg() ?></div>
  </div>
</div>
