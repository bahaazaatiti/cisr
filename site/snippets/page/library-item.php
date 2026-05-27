<?php
  /** @var \Kirby\Cms\Page $page */
  $magnet  = (string) $page->magnet();
  $parsed  = magnet_parse($magnet);
  $kind    = (string) $page->kind() ?: 'other';
  $size    = (string) $page->size_human();
  $langs   = $page->language()->split(',');
  $added   = $page->added()->isNotEmpty() ? $page->added()->toDate('Y-m-d') : null;
  $infohash = $parsed['infohash'] ?? null;
  $crumbs = [[t('nav.home', 'Home'), site()->homePage()->url()],
             [t('nav.library', 'Library'), page('library')?->url()]];
  foreach ($page->parents()->flip() as $p) {
    if ($p->slug() === 'library' || $p->intendedTemplate()->name() !== 'library') continue;
    $crumbs[] = [$p->title()->value(), $p->url()];
  }
  $crumbs[] = [$page->title()->value(), null];
?>
<?php snippet('ui/breadcrumb', ['crumbs' => $crumbs]) ?>

<header class="mb-6">
  <div class="ui-sku"><?= esc(option('brand.sku', site()->title())) ?> / <?= esc(strtoupper(t('nav.library', 'LIBRARY'))) ?> / [<?= esc(strtoupper($kind)) ?>]</div>
  <h1 class="text-xl"><?= esc($page->title()) ?></h1>
  <?php if ($page->summary()->isNotEmpty()): ?>
    <p class="text-sm text-muted-foreground mt-2"><?= esc($page->summary()) ?></p>
  <?php endif ?>
</header>

<dl class="org-spec mb-4">
  <dt><?= t('label.kind', 'Kind') ?></dt>
  <dd><?= esc(t('library.kind.' . $kind, ucfirst($kind))) ?></dd>
  <?php if ($size !== ''): ?>
    <dt><?= t('th.size', 'Size') ?></dt>
    <dd><?= esc($size) ?></dd>
  <?php endif ?>
  <?php if (!empty($langs)): ?>
    <dt><?= t('label.languages', 'Languages') ?></dt>
    <dd><?= esc(strtoupper(implode(' / ', array_map('trim', $langs)))) ?></dd>
  <?php endif ?>
  <?php if ($added): ?>
    <dt><?= t('label.added', 'Added') ?></dt>
    <dd><?= esc($added) ?></dd>
  <?php endif ?>
  <?php if ($infohash): ?>
    <dt><?= t('label.infohash', 'Infohash') ?></dt>
    <dd class="break-all" style="font-size:0.75rem"><?= esc($infohash) ?></dd>
  <?php endif ?>
</dl>

<?php
  // Browser-viewable kinds only get the in-page player.
  $isViewable = in_array($kind, ['video', 'audio', 'image', 'pdf'], true);
?>
<?php if ($magnet): ?>
  <div class="p2p-actions ui-sku mb-3 flex flex-wrap gap-3">
    <?php if ($isViewable): ?>
      <button type="button" class="ui-badge" data-p2p-action="open" data-magnet="<?= esc($magnet) ?>" data-kind="<?= esc($kind) ?>"><?= t('ui.open_player', 'Open in viewer') ?></button>
    <?php endif ?>
    <button type="button" class="ui-badge" data-p2p-action="download" data-magnet="<?= esc($magnet) ?>" data-kind="<?= esc($kind) ?>"><?= t('ui.download', 'Download') ?></button>
    <button type="button" class="ui-badge" data-p2p-action="copy" data-magnet="<?= esc($magnet) ?>"><?= t('ui.copy_magnet', 'Copy magnet') ?></button>
  </div>

  <div class="p2p-status ui-sku mb-2" data-p2p-status></div>

  <?php if ($isViewable): ?>
    <div class="p2p-stage mb-4" data-p2p-stage data-magnet="<?= esc($magnet) ?>" data-kind="<?= esc($kind) ?>"></div>
  <?php endif ?>

  <p class="text-xs text-muted-foreground mb-4"><?= t('ui.privacy_note', 'This viewer uses WebRTC. Your IP is visible to other peers while connected; your browser shares bandwidth.') ?></p>
<?php else: ?>
  <p class="text-muted-foreground"><?= t('msg.no_source', 'No magnet link set.') ?></p>
<?php endif ?>

<?php if ($page->notes()->isNotEmpty()): ?>
  <div class="ui-prose mt-6"><?= $page->notes()->kt() ?></div>
<?php endif ?>

<p class="text-center my-10 ui-sku">* * *</p>
