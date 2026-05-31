<?php
  /** @var \Kirby\Cms\App $kirby */
  /** @var \Kirby\Cms\Site $site */
  /** @var \Kirby\Cms\Page $page */
  $current    = $page;
  $articles   = page('articles');
  $fraternals = page('fraternals');
  $recent     = $articles ? $articles->children()->listed()->sortBy('date', 'desc')->limit(5) : [];
  $langs      = $kirby->languages();
  $homeUrl    = $site->homePage()->url();
  $articlesUrl = $articles ? $articles->url() : url('articles');
  $fraternalsUrl = $fraternals ? $fraternals->url() : url('fraternals');
?>
<aside class="sidebar" data-sidebar>
  <div class="sidebar-head">
    <?php
      // Sidebar mark: panel-managed via site.yml's `logo` field. Falls back to
      // the bundled sign.svg so the slot is always populated.
      $logoFile = $site->logo()->toFile();
      $logoUrl  = $logoFile ? $logoFile->url() : url('assets/img/logo.svg');
    ?>
    <img class="sidebar-sign" src="<?= esc($logoUrl) ?>" alt="" aria-hidden="true" width="200" height="230">
    <div class="ui-sku"><?= esc(option('brand.sku', site()->title())) ?> / <?= esc(option('brand.site_id', '')) ?></div>
    <h2><?= esc($site->title()) ?></h2>
    <?php if ($site->tagline()->isNotEmpty()): ?>
      <div class="text-xs text-muted-foreground mt-1"><?= esc($site->tagline()) ?></div>
    <?php endif ?>
  </div>

  <nav class="sidebar-nav" aria-label="<?= t('nav.sections', 'Sections') ?>">
    <div class="group-label"><?= t('nav.sections', 'Sections') ?></div>
    <ul>
      <?php
        $isHome      = $current->isHomePage();
        $isArticles  = $current->is($articles);
        $isFrats     = $fraternals && ($current->is($fraternals) || $current->parents()->find($fraternals->id()));
      ?>
      <li><a class="nav-item<?= $isHome ? ' active' : '' ?>"<?= $isHome ? ' aria-current="page"' : '' ?> href="<?= $homeUrl ?>" data-link><?= t('nav.home', 'Home') ?></a></li>
      <li><a class="nav-item<?= $isArticles ? ' active' : '' ?>"<?= $isArticles ? ' aria-current="page"' : '' ?> href="<?= $articlesUrl ?>" data-link><?= t('nav.articles', 'Articles') ?></a></li>
      <li><a class="nav-item<?= $isFrats ? ' active' : '' ?>"<?= $isFrats ? ' aria-current="page"' : '' ?> href="<?= $fraternalsUrl ?>" data-link><?= t('nav.fraternals', 'Fraternal') ?></a></li>
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
                <span class="ui-sku block"><?= esc($a->sku()) ?></span>
              <?php endif ?>
            </a>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </nav>

  <?php
    $stamp   = build_stamp();
    $mirrors = mirrors_list(50);
  ?>
  <div class="sidebar-foot">
    <div class="sidebar-foot-row">
      <div class="flex gap-2 items-baseline flex-1" role="group" aria-label="<?= t('ui.language', 'Language') ?>">
        <?php foreach ($langs as $i => $l):
          $isCurrent = $l->code() === $kirby->language()->code();
        ?>
          <?php if ($i): ?><span aria-hidden="true">·</span><?php endif ?>
          <a href="<?= $page->url($l->code()) ?>" hreflang="<?= esc($l->code()) ?>"<?= $isCurrent ? ' class="font-bold" aria-current="page"' : '' ?>>
            <?= esc(strtoupper($l->code())) ?>
          </a>
        <?php endforeach ?>
      </div>
      <button data-theme-toggle class="ui-badge" type="button" aria-label="<?= t('ui.toggle_theme', 'Toggle theme') ?>" title="<?= t('ui.toggle_theme', 'Toggle theme') ?>">◐</button>
    </div>
    <?php if (!empty($stamp['sha'])): ?>
      <div class="sidebar-foot-meta ui-sku">
        <?php if ($mirrors): ?>
          <details class="mirror-popover" title="<?= esc($stamp['sha_full'] ?? $stamp['sha']) ?>">
            <summary class="mirror-summary">
              <?= t('mirror.label', 'MIRROR') ?> · <?= esc($stamp['sha']) ?>
              <?php if (!empty($stamp['built_at'])): ?> · <?= esc(substr($stamp['built_at'], 0, 10)) ?><?php endif ?>
            </summary>
            <ul class="ctxmenu" role="menu" aria-label="<?= t('mirror.also_at', 'Also at:') ?>">
              <?php foreach ($mirrors as $m): ?>
                <li role="none">
                  <a role="menuitem" href="<?= esc($m['url']) ?>" rel="noopener" target="_blank">
                    <span class="ctxmenu-item-name"><?= esc($m['name']) ?> <span aria-hidden="true">↗</span></span>
                    <?php if (!empty($m['note'])): ?>
                      <span class="ctxmenu-item-note"><?= esc($m['note']) ?></span>
                    <?php endif ?>
                  </a>
                </li>
              <?php endforeach ?>
            </ul>
          </details>
        <?php else: ?>
          <div title="<?= esc($stamp['sha_full'] ?? $stamp['sha']) ?>">
            <?= t('mirror.label', 'MIRROR') ?> · <?= esc($stamp['sha']) ?>
            <?php if (!empty($stamp['built_at'])): ?> · <?= esc(substr($stamp['built_at'], 0, 10)) ?><?php endif ?>
          </div>
        <?php endif ?>
      </div>
    <?php endif ?>
  </div>
</aside>

<button class="sidebar-toggle" data-sidebar-toggle type="button" aria-label="<?= t('ui.toggle_sidebar', 'Toggle sidebar') ?>">≡</button>
