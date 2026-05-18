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
  <h1 class="text-xl" data-title="<?= esc($page->fullTitle()) ?>"><?= esc($page->title()) ?></h1>
  <?php if ($page->intro()->isNotEmpty()): ?>
    <p class="text-sm text-muted-foreground mt-2"><?= esc($page->intro()) ?></p>
  <?php endif ?>
</header>

<?php if (count($orgs) === 0): ?>
  <p class="text-muted-foreground"><?= t('msg.no_fraternals', 'No organizations listed yet.') ?></p>
<?php else: ?>
  <?php foreach ($groups as $key => $label): ?>
    <?php $items = $byAffinity[$key] ?? []; if (!$items) continue; ?>
    <section class="frat-group mb-6">
      <div class="frat-group-head">
        <h2 class="usgc-badge"><?= esc($label) ?></h2>
        <span class="usgc-sku"><?= count($items) ?> <?= t('label.entries', 'entries') ?></span>
      </div>

      <ul class="frat-list">
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
        ?>
          <li class="frat-card">
            <div class="frat-meta">
              <?php if ($sku): ?><span><?= esc($sku) ?></span><?php endif ?>
              <?php if ($type): ?><span class="frat-dot">·</span><span><?= esc(strtoupper(t('frat.type.' . $type, $type))) ?></span><?php endif ?>
              <?php if (!empty($langs)): ?>
                <span class="frat-dot">·</span>
                <span><?= esc(strtoupper(implode('/', array_map('trim', $langs)))) ?></span>
              <?php endif ?>
              <?php if ($founded): ?><span class="frat-dot">·</span><span><?= t('frat.est', 'EST.') ?> <?= esc($founded) ?></span><?php endif ?>
              <?php if ($region): ?><span class="frat-region"><?= esc($region) ?></span><?php endif ?>
            </div>

            <div class="frat-name">
              <h3><?= esc($o->title()) ?></h3>
              <?php if ($native): ?><div class="frat-native"><?= esc($native) ?></div><?php endif ?>
            </div>

            <?php if ($o->summary()->isNotEmpty()): ?>
              <p class="frat-summary"><?= esc($o->summary()) ?></p>
            <?php endif ?>

            <div class="frat-foot">
              <?php if ($hp): ?>
                <a class="frat-link" href="<?= esc($hp) ?>" rel="noopener" target="_blank">
                  <?= esc($host) ?> <span aria-hidden="true">↗</span>
                </a>
              <?php endif ?>
              <?php if ($hasMore): ?>
                <a class="frat-more usgc-sku" href="<?= $o->url() ?>" data-link><?= t('label.more', 'more') ?> →</a>
              <?php endif ?>
            </div>
          </li>
        <?php endforeach ?>
      </ul>
    </section>
  <?php endforeach ?>
<?php endif ?>

<p class="text-center my-10 usgc-sku">* * *</p>
