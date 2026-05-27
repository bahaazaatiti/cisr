<?php
  /** @var \Kirby\Cms\Page $page */
  // Sorting is client-side (JS reads data-sort-* attrs from rows). The default
  // server-side order is date desc; the JS toggles to other orders without
  // touching the URL — works on static hosts where ?sort= can't be honored.
  $list = $page->children()->listed()->sortBy('date', 'desc');
  $sortOptions = [
    ['key' => 'date',  'dir' => 'desc', 'label' => t('sort.latest', 'Latest')],
    ['key' => 'sku',   'dir' => 'asc',  'label' => t('sort.sku',    'Index')],
    ['key' => 'alpha', 'dir' => 'asc',  'label' => t('sort.alpha',  'A–Z')],
  ];
?>
<?php snippet('ui/breadcrumb', ['crumbs' => [
  [t('nav.home', 'Home'), site()->homePage()->url()],
  [$page->title()->value(), null],
]]) ?>
<header class="mb-6">
  <div class="usgc-sku">CISR / INDEX</div>
  <h1 class="text-xl"><?= esc($page->title()) ?></h1>
</header>

<nav class="sort-bar usgc-sku" aria-label="<?= t('ui.sort', 'Sort') ?>">
  <span><?= t('sort.label', 'Sort:') ?></span>
  <?php foreach ($sortOptions as $i => $o): ?>
    <button type="button"
            data-sort="<?= esc($o['key']) ?>"
            data-sort-dir="<?= esc($o['dir']) ?>"
            class="<?= $i === 0 ? 'active' : '' ?>"><?= esc($o['label']) ?></button>
  <?php endforeach ?>
</nav>

<?php if (count($list) === 0): ?>
  <p class="text-muted-foreground"><?= t('msg.no_articles', 'No articles yet.') ?></p>
<?php else: ?>
  <table class="usgc-table" data-sortable>
    <thead>
      <tr>
        <th class="w-32"><?= t('th.date', 'Date') ?></th>
        <th class="w-28"><?= t('th.sku', 'SKU') ?></th>
        <th><?= t('th.title', 'Title') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($list as $a):
        $dateStr = $a->date()->toDate('Y-m-d') ?: '';
        $skuStr  = (string) $a->sku();
        $alphaStr = strtolower((string) $a->title());
      ?>
        <tr data-sort-date="<?= esc($dateStr) ?>" data-sort-sku="<?= esc($skuStr) ?>" data-sort-alpha="<?= esc($alphaStr) ?>">
          <td class="whitespace-nowrap"><?= $dateStr ?: '—' ?></td>
          <td class="whitespace-nowrap"><?= esc($a->sku()->or('—')) ?></td>
          <td><a href="<?= $a->url() ?>" data-link><?= esc($a->title()) ?></a></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
<?php endif ?>
