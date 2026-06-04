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
<?php /* Broadcast viewer ships only when a broadcast is live. It joins the room
         receive-only and features the signature-verified broadcaster. */ ?>
<?php if (broadcast_active()): ?>
<script src="<?= url('assets/js/broadcast.min.js') ?>"
        data-comm-vendor="<?= url('assets/js/vendor/trystero.min.js') ?>"
        data-broadcast-room="<?= esc(broadcast_room(), 'attr') ?>"
        data-broadcast-pubkey="<?= esc(broadcast_pubkey(), 'attr') ?>"
        data-broadcast-relay="<?= broadcast_relay() ? '1' : '0' ?>"
        data-broadcast-relays="<?= esc($commRelays, 'attr') ?>"
        data-broadcast-turn="<?= esc(implode('|;|', trackers_list('turn')), 'attr') ?>"
        defer></script>
<?php endif ?>
</body>
</html>
