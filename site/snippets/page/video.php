<?php
  /** @var \Kirby\Cms\Page $page */
  $src = $page->videoEmbedUrl();
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
  </div>
  <h1 class="text-xl" data-title><?= esc($page->title()) ?></h1>
</header>

<div class="vid-stage mb-4">
  <?php if ($src): ?>
    <iframe class="vid-frame" src="<?= esc($src) ?>" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
  <?php else: ?>
    <div class="vid-frame flex items-center justify-center text-[color:var(--muted-foreground)]"><?= t('msg.no_source', 'No video source.') ?></div>
  <?php endif ?>
</div>

<?php if ($page->summary()->isNotEmpty()): ?>
  <p class="text-sm"><?= esc($page->summary()) ?></p>
<?php endif ?>

<p class="text-center my-10 usgc-sku">* * *</p>
