  </main>
  <?php snippet('aside-right') ?>
  <?php snippet('ticker') ?>
</div>

<script src="<?= url('assets/js/app.min.js') ?>" defer></script>
<script src="<?= url('assets/js/p2p.min.js') ?>"
        data-vendor="<?= url('assets/js/vendor/webtorrent.min.js') ?>"
        data-sw="<?= url('sw.min.js') ?>"
        defer></script>
<?php
  // Relay list shared by comms + broadcast viewer (TRACKERS.md → option → JS).
  $commRelays = implode(' ', trackers_list('relays') ?: (array) option('comm.relays', []));
?>
<script src="<?= url('assets/js/comm.min.js') ?>"
        data-comm-vendor="<?= url('assets/js/vendor/trystero.min.js') ?>"
        data-comm-relays="<?= esc($commRelays, 'attr') ?>"
        data-comm-broadcast-room="<?= esc(broadcast_active() ? broadcast_room() : '', 'attr') ?>"
        defer></script>
<?php /* Ship the ticker module only when the crawl is actually on — a fresh
         fork (ticker off) pays nothing for it. */ ?>
<?php if (ticker_active()): ?>
<script src="<?= url('assets/js/ticker.min.js') ?>" defer></script>
<?php endif ?>
<?php /* The broadcast VIEWER script is not here — it ships inside the hero snippet
         (page/broadcast-hero.php) so it loads only on the home page that has the
         hero, not site-wide. app.js re-executes #panel scripts on SPA-nav, so it
         still (re)starts when you navigate home. (comm.js above carries
         data-comm-broadcast-room for the broadcaster side, which IS site-wide.) */ ?>
</body>
</html>
