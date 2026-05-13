<?php
  /** @var \Kirby\Cms\App $kirby */
  /** @var \Kirby\Cms\Site $site */
  /** @var \Kirby\Cms\Page $page */
  $current   = $page;
  $articles  = page('articles');
  $recent    = $articles ? $articles->children()->listed()->sortBy('date', 'desc')->limit(8) : [];
  $langs     = $kirby->languages();
  $homeUrl   = $site->homePage()->url();
  $articlesUrl = $articles ? $articles->url() : url('articles');
?>
<aside class="sidebar" data-sidebar>
  <div class="sidebar-head">
    <div class="usgc-sku">CISR / TR-100</div>
    <div class="font-bold uppercase tracking-[0.08em]"><?= esc($site->title()) ?></div>
    <?php if ($site->tagline()->isNotEmpty()): ?>
      <div class="text-xs text-[color:var(--muted-foreground)] mt-1"><?= esc($site->tagline()) ?></div>
    <?php endif ?>
  </div>

  <nav class="sidebar-nav">
    <div class="group-label">Sections</div>
    <ul>
      <li><a class="nav-item<?= $current->isHomePage() ? ' active' : '' ?>" href="<?= $homeUrl ?>" data-link><?= t('nav.home', 'Home') ?></a></li>
      <li><a class="nav-item<?= $current->is($articles) ? ' active' : '' ?>" href="<?= $articlesUrl ?>" data-link><?= t('nav.articles', 'Articles') ?></a></li>
    </ul>

    <?php if (count($recent)): ?>
      <div class="group-label mt-3"><?= t('nav.recent', 'Recent') ?></div>
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
      <?php $first = true; foreach ($langs as $l): ?>
        <?php if (!$first): ?><span aria-hidden="true">·</span><?php endif; $first = false; ?>
        <a href="<?= $page->url($l->code()) ?>" hreflang="<?= esc($l->code()) ?>"<?= $kirby->language()->code() === $l->code() ? ' class="font-bold"' : '' ?>>
          <?= esc(strtoupper($l->code())) ?>
        </a>
      <?php endforeach ?>
    </div>
    <button data-theme-toggle class="usgc-badge" type="button" aria-label="Toggle theme" title="Toggle light/dark">◐</button>
  </div>
</aside>

<button class="sidebar-toggle" data-sidebar-toggle type="button" aria-label="Toggle sidebar">≡</button>
