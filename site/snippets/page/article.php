<?php
  /** @var \Kirby\Cms\Page $page */
  $articles = page('articles');
?>
<?php snippet('ui/breadcrumb', ['crumbs' => [
  [t('nav.home', 'Home'), site()->homePage()->url()],
  [t('nav.articles', 'Articles'), $articles ? $articles->url() : null],
  [$page->title()->value(), null],
]]) ?>
<header class="mb-6">
  <div class="ui-sku flex items-center gap-2">
    <?php if ($page->sku()->isNotEmpty()): ?><span><?= esc($page->sku()) ?></span><?php endif ?>
    <?php if ($page->date()->isNotEmpty()): ?><span>· <?= $page->date()->toDate('Y-m-d') ?></span><?php endif ?>
    <?php snippet('ui/status-badge', ['page' => $page]) ?>
  </div>
  <h1 class="text-xl"><?= esc($page->title()) ?></h1>
  <?php if ($page->summary()->isNotEmpty()): ?>
    <p class="text-sm text-muted-foreground mt-2"><?= esc($page->summary()) ?></p>
  <?php endif ?>
</header>

<div class="ui-prose">
  <?= $page->body()->toBlocks() ?>
</div>

<p class="text-center my-10 ui-sku">* * *</p>
