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
  <div class="ui-sku"><?= esc(option('brand.sku', site()->title())) ?> / <?= esc(strtoupper(t('nav.fraternals', 'FRATERNAL'))) ?></div>
  <h1 class="text-xl"><?= esc($page->title()) ?></h1>
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
      <div class="org-group-head">
        <h2 class="ui-badge"><?= esc($label) ?></h2>
        <span class="ui-sku"><?= count($items) ?> <?= t('label.entries', 'entries') ?></span>
      </div>

      <table class="ui-table org-table">
        <thead>
          <tr>
            <th class="org-col-sku"><?= t('th.sku', 'SKU') ?></th>
            <th><?= t('th.name', 'Name') ?></th>
            <th class="org-col-link" aria-hidden="true"></th>
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
            $hasMore = $o->isRich();
            $meta = [];
            if ($type)          { $meta[] = strtoupper(t('frat.type.' . $type, $type)); }
            if (!empty($langs)) { $meta[] = strtoupper(implode('/', array_map('trim', $langs))); }
            if ($region)        { $meta[] = strtoupper($region); }
            if ($founded)       { $meta[] = t('frat.est', 'EST.') . ' ' . $founded; }
          ?>
            <tr>
              <td class="ui-sku org-cell-sku"><?= $sku ? esc($sku) : '—' ?></td>
              <td class="org-cell-name">
                <?php if ($hasMore): ?>
                  <a href="<?= $o->url() ?>" data-link class="org-title"><?= esc($o->title()) ?></a>
                <?php else: ?>
                  <span class="org-title"><?= esc($o->title()) ?></span>
                <?php endif ?>
                <?php if ($native): ?><div class="org-native"><?= esc($native) ?></div><?php endif ?>
                <?php if ($meta): ?>
                  <div class="org-meta ui-sku"><?= esc(implode(' · ', $meta)) ?></div>
                <?php endif ?>
              </td>
              <td class="org-cell-link">
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

<p class="text-center my-10 ui-sku">* * *</p>
