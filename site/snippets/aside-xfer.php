<?php
  /** @var \Kirby\Cms\Site $site */
?>
<button class="drawer-toggle drawer-toggle-xfer"
        data-drawer-toggle="xfer"
        type="button"
        aria-controls="drawer-xfer"
        aria-expanded="false">
  <span class="xfer-tab-inner"><span aria-hidden="true">⇅</span> <?= t('xfer.title', 'TRANSFER') ?><span class="xfer-count" data-xfer-count></span></span>
</button>

<div class="drawer drawer-xfer" id="drawer-xfer" data-drawer="xfer" hidden role="dialog" aria-label="<?= t('xfer.region', 'Transfers') ?>">
  <div class="drawer-tabs xfer-head" role="group" aria-label="<?= t('xfer.region', 'Transfers') ?>">
    <span class="xfer-title"><?= t('xfer.title', 'TRANSFER') ?></span>
    <button type="button" class="xfer-btn xfer-add" data-xfer-add
            aria-label="<?= esc(t('xfer.add', 'Seed a file from your device'), 'attr') ?>"
            title="<?= esc(t('xfer.add', 'Seed a file from your device'), 'attr') ?>">+</button>
    <button type="button" class="xfer-btn xfer-add" data-xfer-magnet
            aria-label="<?= esc(t('xfer.magnet', 'Add a magnet link'), 'attr') ?>"
            title="<?= esc(t('xfer.magnet', 'Add a magnet link'), 'attr') ?>">∩</button>
    <span class="ui-sku xfer-summary" data-xfer-summary aria-live="polite"></span>
    <button type="button" data-drawer-close class="drawer-x" aria-label="<?= t('ui.close', 'Close') ?>">✕</button>
  </div>
  <div class="drawer-panels">
    <div class="drawer-panel xfer-panel">
      <form class="xfer-magnet-row" data-xfer-magnet-row hidden onsubmit="return false">
        <input class="xfer-in" data-xfer-magnet-input name="xfer-magnet" autocomplete="off" spellcheck="false"
               placeholder="<?= esc(t('xfer.magnet_ph', 'paste a magnet:?xt=… link'), 'attr') ?>"
               aria-label="<?= esc(t('xfer.magnet', 'Add a magnet link'), 'attr') ?>">
        <button type="submit" class="ui-badge" data-xfer-magnet-go><?= t('xfer.magnet_go', 'ADD') ?></button>
      </form>
      <ul class="xfer-list" data-xfer-list aria-live="polite"
          data-st-connecting="<?= esc(t('xfer.st_connecting', 'connecting…'), 'attr') ?>"
          data-st-downloading="<?= esc(t('xfer.st_downloading', 'downloading'), 'attr') ?>"
          data-st-seeding="<?= esc(t('xfer.st_seeding', 'seeding'), 'attr') ?>"
          data-st-paused="<?= esc(t('xfer.st_paused', 'paused'), 'attr') ?>"
          data-st-saved="<?= esc(t('xfer.st_saved', 'saved'), 'attr') ?>"
          data-lbl-pause="<?= esc(t('xfer.pause', 'Pause'), 'attr') ?>"
          data-lbl-resume="<?= esc(t('xfer.resume', 'Resume'), 'attr') ?>"
          data-lbl-remove="<?= esc(t('xfer.remove', 'Remove'), 'attr') ?>"
          data-lbl-copy="<?= esc(t('xfer.copy', 'Copy magnet'), 'attr') ?>"></ul>
      <div class="ui-sku xfer-empty" data-xfer-empty><?= t('xfer.empty', 'No transfers yet. Download or seed an item to see it here.') ?></div>
      <input type="file" data-xfer-file multiple hidden aria-hidden="true">
    </div>
  </div>
  <a class="xfer-foot ui-sku" href="https://webtorrent.io/desktop/" target="_blank" rel="noopener noreferrer"><?= t('xfer.desktop', 'Get WebTorrent Desktop') ?> ↗</a>
</div>
