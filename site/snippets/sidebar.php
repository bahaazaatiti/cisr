<?php
  /** @var \Kirby\Cms\App $kirby */
  /** @var \Kirby\Cms\Site $site */
  /** @var \Kirby\Cms\Page $page */
  $current   = $page;
  $articles  = page('articles');
  $recent    = $articles ? $articles->children()->listed()->sortBy('date', 'desc')->limit(5) : [];
  $langs     = $kirby->languages();
  $homeUrl   = $site->homePage()->url();
  $articlesUrl = $articles ? $articles->url() : url('articles');
?>
<aside class="sidebar" data-sidebar>
  <div class="sidebar-head">
    <img class="sidebar-sign" src="<?= url('assets/img/sign.svg') ?>" alt="" aria-hidden="true" width="200" height="230">
    <div class="usgc-sku">CISR / TR-100</div>
    <div class="font-bold uppercase tracking-[0.08em]"><?= esc($site->title()) ?></div>
    <?php if ($site->tagline()->isNotEmpty()): ?>
      <div class="text-xs text-muted-foreground mt-1"><?= esc($site->tagline()) ?></div>
    <?php endif ?>
  </div>

  <nav class="sidebar-nav">
    <div class="group-label"><?= t('nav.sections', 'Sections') ?></div>
    <ul>
      <li><a class="nav-item<?= $current->isHomePage() ? ' active' : '' ?>" href="<?= $homeUrl ?>" data-link><?= t('nav.home', 'Home') ?></a></li>
      <li><a class="nav-item<?= $current->is($articles) ? ' active' : '' ?>" href="<?= $articlesUrl ?>" data-link><?= t('nav.articles', 'Articles') ?></a></li>
    </ul>

    <?php
      $featured = page('home')?->featured()?->toPages() ?? new \Kirby\Cms\Pages();
    ?>
    <?php if (count($featured)): ?>
      <div class="group-label mt-3"><?= t('nav.featured', 'Featured') ?></div>
      <ul>
        <?php foreach ($featured as $f): ?>
          <li>
            <a class="nav-item nav-featured<?= $current->is($f) ? ' active' : '' ?>" href="<?= $f->url() ?>" data-link title="<?= esc($f->title()) ?>">
              <?= esc($f->title()) ?>
            </a>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>

    <?php if (count($recent)): ?>
      <div class="group-label mt-3"><?= t('nav.latest', 'Latest') ?></div>
      <ul>
        <?php foreach ($recent as $a): ?>
          <li>
            <a class="nav-item<?= $current->is($a) ? ' active' : '' ?>" href="<?= $a->url() ?>" data-link title="<?= esc($a->title()) ?>">
              <span class="block truncate"><?= esc($a->title()) ?></span>
              <?php if ($a->sku()->isNotEmpty()): ?>
                <span class="usgc-sku block"><?= esc($a->sku()) ?></span>
              <?php endif ?>
            </a>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </nav>

  <div class="sidebar-foot">
    <div class="flex gap-2 items-baseline flex-1">
      <?php foreach ($langs as $i => $l): ?>
        <?php if ($i): ?><span aria-hidden="true">·</span><?php endif ?>
        <a href="<?= $page->url($l->code()) ?>" hreflang="<?= esc($l->code()) ?>"<?= $l->code() === $kirby->language()->code() ? ' class="font-bold"' : '' ?>>
          <?= esc(strtoupper($l->code())) ?>
        </a>
      <?php endforeach ?>
    </div>
    <button data-theme-toggle class="usgc-badge" type="button" aria-label="Toggle theme" title="Toggle light/dark">◐</button>
  </div>
</aside>

<button class="sidebar-toggle" data-sidebar-toggle type="button" aria-label="Toggle sidebar">≡</button>
