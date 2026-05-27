<?php
  /** @var \Kirby\Cms\Page $page */
  $parent  = $page->parent() ?? page('fraternals');
  $hp      = (string) $page->homepage();
  $native  = (string) $page->native_name();
  $type    = (string) $page->type();
  $region  = (string) $page->region();
  $founded = (string) $page->founded();
  $sku     = (string) $page->sku();
  $aff     = (string) $page->affinity()->or('aligned');
  $langs   = $page->languages()->split(',');
  $verified = $page->verified()->isNotEmpty() ? $page->verified()->toDate('Y-m-d') : null;
  $host = $hp ? preg_replace('~^www\.~i', '', parse_url($hp, PHP_URL_HOST) ?: $hp) : '';
?>
<?php snippet('ui/breadcrumb', ['crumbs' => [
  [t('nav.home', 'Home'), site()->homePage()->url()],
  [t('nav.fraternals', 'Fraternal'), $parent ? $parent->url() : null],
  [$page->title()->value(), null],
]]) ?>

<header class="mb-6">
  <div class="usgc-sku flex items-center gap-2 flex-wrap">
    <?php if ($sku): ?><span><?= esc($sku) ?></span><?php endif ?>
    <span>· <?= esc(strtoupper(t('frat.aff.' . $aff, $aff))) ?></span>
    <?php if ($type): ?><span>· <?= esc(strtoupper(t('frat.type.' . $type, $type))) ?></span><?php endif ?>
  </div>
  <h1 class="text-xl"><?= esc($page->title()) ?></h1>
  <?php if ($native): ?><div class="frat-native mt-1"><?= esc($native) ?></div><?php endif ?>
  <?php if ($page->summary()->isNotEmpty()): ?>
    <p class="text-sm text-muted-foreground mt-2"><?= esc($page->summary()) ?></p>
  <?php endif ?>
</header>

<dl class="frat-spec">
  <?php if ($region): ?><dt><?= t('frat.field.region', 'Region') ?></dt><dd><?= esc($region) ?></dd><?php endif ?>
  <?php if (!empty($langs)): ?>
    <dt><?= t('frat.field.languages', 'Languages') ?></dt>
    <dd><?= esc(strtoupper(implode(' · ', array_map('trim', $langs)))) ?></dd>
  <?php endif ?>
  <?php if ($founded): ?><dt><?= t('frat.field.founded', 'Founded') ?></dt><dd><?= esc($founded) ?></dd><?php endif ?>
  <?php if ($hp): ?>
    <dt><?= t('frat.field.homepage', 'Homepage') ?></dt>
    <dd><a href="<?= esc($hp) ?>" rel="noopener" target="_blank"><?= esc($host) ?> ↗</a></dd>
  <?php endif ?>
  <?php if ($verified): ?>
    <dt><?= t('frat.field.verified', 'Last verified') ?></dt>
    <dd><?= esc($verified) ?></dd>
  <?php endif ?>
</dl>

<?php if ($page->notes()->isNotEmpty()): ?>
  <div class="usgc-rule my-6" role="separator"></div>
  <section class="prose-usgc">
    <?= $page->notes()->kt() ?>
  </section>
<?php endif ?>

<p class="text-center my-10 usgc-sku">* * *</p>
