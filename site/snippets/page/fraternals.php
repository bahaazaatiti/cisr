<?php
  /** @var \Kirby\Cms\Page $page */
  $groups = [
    'sister'     => t('frat.group.sister',     'Sister sections'),
    'aligned'    => t('frat.group.aligned',    'Aligned organizations'),
    'friendly'   => t('frat.group.friendly',   'Friendly publications'),
    'historical' => t('frat.group.historical', 'Historical / archive'),
  ];

  $orgs = $page->children()->listed();
  $byAffinity = [];
  foreach ($orgs as $o) {
    $a = (string) $o->affinity()->or('aligned');
    if (!isset($groups[$a])) $a = 'aligned';
    $byAffinity[$a][] = $o;
  }

  $hostOf = function (string $url): string {
    $h = parse_url($url, PHP_URL_HOST);
    return $h ? preg_replace('~^www\.~i', '', $h) : $url;
  };
?>
<?php snippet('ui/breadcrumb', ['crumbs' => [
  [t('nav.home', 'Home'), site()->homePage()->url()],
  [$page->title()->value(), null],
]]) ?>

<header class="mb-6">
  <div class="usgc-sku">CISR / FRATERNAL</div>
  <h1 class="text-xl" data-title="<?= esc($page->fullTitle()) ?>" data-description="<?= esc($page->metaDescription()) ?>"><?= esc($page->title()) ?></h1>
  <?php if ($page->intro()->isNotEmpty()): ?>
    <p class="text-sm text-muted-foreground mt-2"><?= esc($page->intro()) ?></p>
  <?php endif ?>
</header>

<?php if (count($orgs) === 0): ?>
  <p class="text-muted-foreground"><?= t('msg.no_fraternals', 'No organizations listed yet.') ?></p>
<?php else: ?>
  <?php foreach ($groups as $key => $label): ?>
    <?php $items = $byAffinity[$key] ?? []; if (!$items) continue; ?>
    <section class="mb-8">
      <div class="frat-group-head">
        <h2 class="usgc-badge"><?= esc($label) ?></h2>
        <span class="usgc-sku"><?= count($items) ?> <?= t('label.entries', 'entries') ?></span>
      </div>

      <table class="usgc-table frat-table">
        <thead>
          <tr>
            <th class="frat-col-sku"><?= t('th.sku', 'SKU') ?></th>
            <th><?= t('th.name', 'Name') ?></th>
            <th class="frat-col-link" aria-hidden="true"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $o):
            $hp      = (string) $o->homepage();
            $host    = $hp ? $hostOf($hp) : '';
            $native  = (string) $o->native_name();
            $type    = (string) $o->type();
            $region  = (string) $o->region();
            $founded = (string) $o->founded();
            $sku     = (string) $o->sku();
            $langs   = $o->languages()->split(',');
            $hasMore = $o->notes()->isNotEmpty();
            $meta = [];
            if ($type)          { $meta[] = strtoupper(t('frat.type.' . $type, $type)); }
            if (!empty($langs)) { $meta[] = strtoupper(implode('/', array_map('trim', $langs))); }
            if ($region)        { $meta[] = strtoupper($region); }
            if ($founded)       { $meta[] = t('frat.est', 'EST.') . ' ' . $founded; }
          ?>
            <tr>
              <td class="usgc-sku frat-cell-sku"><?= $sku ? esc($sku) : '—' ?></td>
              <td class="frat-cell-name">
                <?php if ($hasMore): ?>
                  <a href="<?= $o->url() ?>" data-link class="frat-title"><?= esc($o->title()) ?></a>
                <?php else: ?>
                  <span class="frat-title"><?= esc($o->title()) ?></span>
                <?php endif ?>
                <?php if ($native): ?><div class="frat-native"><?= esc($native) ?></div><?php endif ?>
                <?php if ($meta): ?>
                  <div class="frat-meta usgc-sku"><?= esc(implode(' · ', $meta)) ?></div>
                <?php endif ?>
              </td>
              <td class="frat-cell-link">
                <?php if ($hp): ?>
                  <a href="<?= esc($hp) ?>" rel="noopener" target="_blank" title="<?= esc($host) ?>" aria-label="<?= esc($host) ?>"><span aria-hidden="true">↗</span></a>
                <?php endif ?>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </section>
  <?php endforeach ?>
<?php endif ?>

<p class="text-center my-10 usgc-sku">* * *</p>
