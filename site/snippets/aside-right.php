<?php
  $library = page('library');
  $videos  = page('videos');
?>
<aside class="aside-right" data-aside-right aria-label="<?= t('media.region', 'Media') ?>">
  <section class="ar-half ar-library" data-mode="gui">
    <header class="ar-head">
      <?php $libPage = page('library'); ?>
      <?php if ($libPage): ?>
        <a class="ar-title" href="<?= $libPage->url() ?>" data-link><?= t('media.library', 'LIBRARY') ?></a>
      <?php else: ?>
        <span class="ar-title"><?= t('media.library', 'LIBRARY') ?></span>
      <?php endif ?>
      <div class="ar-tools">
        <button data-mode-set="gui"  class="ar-mode active" type="button" aria-label="<?= t('media.gui',  'GUI')  ?>" title="<?= t('media.gui',  'GUI')  ?>">▦</button>
        <button data-mode-set="list" class="ar-mode"        type="button" aria-label="<?= t('media.list', 'LIST') ?>" title="<?= t('media.list', 'LIST') ?>">≣</button>
      </div>
    </header>
    <div class="ar-body">
      <div class="lib-bar">
        <button type="button" class="lib-up" data-lib-up disabled title="<?= t('media.up', 'Up') ?>"><span aria-hidden="true">↑</span></button>
        <span class="lib-cwd" data-lib-cwd>/</span>
        <span class="ui-sku lib-status" data-p2p-status></span>
      </div>
      <div class="lib-gui"
           data-lib-tree-src="<?= url('library.json') ?>"
           data-lib-empty="<?= esc(t('msg.empty_folder', 'empty'), 'attr') ?>"
           data-lib-path="">
        <div class="lib-grid" data-lib-grid></div>
      </div>
      <div class="lib-list" hidden>
        <table class="lib-table">
          <thead><tr>
            <th class="w-6"></th>
            <th><?= t('th.name', 'NAME') ?></th>
            <th class="w-12"><?= t('th.size', 'SIZE') ?></th>
            <th class="w-20"><?= t('th.added', 'ADDED') ?></th>
          </tr></thead>
          <tbody data-lib-list-body></tbody>
        </table>
        <div class="ui-sku" data-lib-list-empty hidden><?= t('msg.empty_folder', 'empty') ?></div>
      </div>
    </div>
  </section>

  <section class="ar-half ar-video">
    <header class="ar-head">
      <?php $vidPage = page('videos'); ?>
      <?php if ($vidPage): ?>
        <a class="ar-title" href="<?= $vidPage->url() ?>" data-link><?= t('media.video', 'VIDEO') ?></a>
      <?php else: ?>
        <span class="ar-title"><?= t('media.video', 'VIDEO') ?></span>
      <?php endif ?>
    </header>
    <div class="vid-stage">
      <div id="player" class="vid-frame vid-frame-empty">
        <span class="ui-sku" data-player-placeholder><?= t('msg.pick_video', 'PICK A VIDEO') ?></span>
      </div>
    </div>
    <div class="ui-sku ar-p2p-status" data-ar-p2p-status></div>
    <ul class="vid-list">
      <?php if ($videos): foreach ($videos->children()->listed()->sortBy('date', 'desc') as $v):
        $isYT     = $v->hasYouTubeSource();
        $isMagnet = $v->hasMagnetSource();
        if (!$isYT && !$isMagnet) continue;
        $vsrc     = $isYT ? $v->videoEmbedUrl() : null;
        $vmagnet  = $isMagnet ? (string) $v->magnet() : null;
        $badge    = $isMagnet ? 'WT' : 'YT';
      ?>
        <li>
          <button type="button" class="vid-pick"
            data-video
            <?php if ($vsrc): ?>data-vid-src="<?= esc($vsrc) ?>"<?php endif ?>
            <?php if ($vmagnet): ?>data-vid-magnet="<?= esc($vmagnet) ?>"<?php endif ?>
            data-vid-title="<?= esc($v->title()) ?>">
            <span class="vid-pick-icon" aria-hidden="true">▷</span>
            <span class="vid-pick-title"><?= esc($v->title()) ?></span>
            <span class="ui-sku vid-pick-badge"><?= $badge ?></span>
            <?php if ($v->duration()->isNotEmpty()): ?>
              <span class="ui-sku vid-pick-dur"><?= esc($v->duration()) ?></span>
            <?php endif ?>
          </button>
        </li>
      <?php endforeach; endif ?>
    </ul>
  </section>
</aside>

<button class="drawer-toggle" data-drawer-toggle="media" type="button" aria-controls="drawer-media" aria-expanded="false">
  <span aria-hidden="true">▲</span> <?= t('media.media', 'MEDIA') ?>
</button>

<div class="drawer" id="drawer-media" data-drawer="media" hidden>
  <div class="drawer-tabs" role="tablist" aria-label="<?= t('media.tabs', 'Media tabs') ?>">
    <button type="button" data-tab="library" class="active" role="tab" aria-selected="true"><?= t('media.library', 'LIBRARY') ?></button>
    <button type="button" data-tab="video" role="tab" aria-selected="false"><?= t('media.video', 'VIDEO') ?></button>
    <button type="button" data-drawer-close class="drawer-x" aria-label="<?= t('ui.close', 'Close') ?>">✕</button>
  </div>
  <div class="drawer-panels">
    <div data-panel="library" class="drawer-panel"></div>
    <div data-panel="video"   class="drawer-panel" hidden></div>
  </div>
</div>

<?php snippet('aside-comm') ?>

<div id="ctxmenu" class="ctxmenu" hidden>
  <button type="button" data-ctx="open"><?= t('media.open', 'OPEN') ?></button>
  <button type="button" data-ctx="download"><?= t('media.download', 'DOWNLOAD') ?></button>
  <button type="button" data-ctx="copy"><?= t('media.copylink', 'COPY MAGNET') ?></button>
</div>
