<?php
  /** @var \Kirby\Cms\Page $page */
  $hasYT     = $page->hasYouTubeSource();
  $hasMagnet = $page->hasMagnetSource();
  $ytSrc     = $hasYT ? $page->videoEmbedUrl() : null;
  $magnet    = $hasMagnet ? $page->magnetUrl() : null;
?>
<?php snippet('ui/breadcrumb', ['crumbs' => [
  [t('nav.home', 'Home'), site()->homePage()->url()],
  [t('nav.videos', 'Videos'), page('videos')?->url()],
  [$page->title()->value(), null],
]]) ?>

<header class="mb-6">
  <div class="usgc-sku flex items-center gap-2">
    <?php if ($page->date()->isNotEmpty()): ?><span><?= $page->date()->toDate('Y-m-d') ?></span><?php endif ?>
    <?php if ($page->duration()->isNotEmpty()): ?><span>· <?= esc($page->duration()) ?></span><?php endif ?>
    <span>· <?= $hasMagnet ? 'WEBTORRENT' : ($hasYT ? 'YOUTUBE' : '—') ?></span>
  </div>
  <h1 class="text-xl" data-title="<?= esc($page->fullTitle()) ?>" data-description="<?= esc($page->metaDescription()) ?>"><?= esc($page->title()) ?></h1>
</header>

<div class="vid-stage mb-4">
  <?php if ($ytSrc): ?>
    <iframe class="vid-frame" src="<?= esc($ytSrc) ?>" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
  <?php elseif ($magnet): ?>
    <div class="vid-frame p2p-stage" data-p2p-stage data-magnet="<?= esc($magnet) ?>" data-kind="video">
      <button type="button" class="usgc-badge" data-p2p-action="open" data-magnet="<?= esc($magnet) ?>" data-kind="video"><?= t('ui.play', 'Play') ?></button>
    </div>
  <?php else: ?>
    <div class="vid-frame flex items-center justify-center text-muted-foreground"><?= t('msg.no_source', 'No video source.') ?></div>
  <?php endif ?>
</div>

<?php if ($hasMagnet): ?>
  <div class="p2p-status usgc-sku mb-2" data-p2p-status></div>
  <p class="text-xs text-muted-foreground mb-4"><?= t('ui.privacy_note', 'This viewer uses WebRTC. Your IP is visible to other peers while connected; your browser shares bandwidth.') ?></p>
<?php endif ?>

<?php if ($page->summary()->isNotEmpty()): ?>
  <p class="text-sm"><?= esc($page->summary()) ?></p>
<?php endif ?>

<p class="text-center my-10 usgc-sku">* * *</p>
