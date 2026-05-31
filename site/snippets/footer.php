  </main>
  <?php snippet('aside-right') ?>
  <?php snippet('ticker') ?>
</div>

<script src="<?= url('assets/js/app.min.js') ?>" defer></script>
<script src="<?= url('assets/js/p2p.min.js') ?>"
        data-vendor="<?= url('assets/js/vendor/webtorrent.min.js') ?>"
        data-sw="<?= url('sw.min.js') ?>"
        defer></script>
<script src="<?= url('assets/js/comm.min.js') ?>"
        data-comm-vendor="<?= url('assets/js/vendor/trystero.min.js') ?>"
        data-comm-relays="<?= esc(implode(' ', trackers_list('relays') ?: (array) option('comm.relays', [])), 'attr') ?>"
        defer></script>
<?php /* Ship the ticker module only when the crawl is actually on — a fresh
         fork (ticker off) pays nothing for it. */ ?>
<?php if (ticker_active()): ?>
<script src="<?= url('assets/js/ticker.min.js') ?>" defer></script>
<?php endif ?>
</body>
</html>
