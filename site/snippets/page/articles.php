<?php
  /** @var \Kirby\Cms\Page $page */
  $sort = in_array(get('sort'), ['latest', 'sku', 'alpha'], true) ? get('sort') : 'latest';
  $list = match ($sort) {
    'sku'   => $page->children()->listed()->sortBy('sku', 'asc', SORT_NATURAL | SORT_FLAG_CASE),
    'alpha' => $page->children()->listed()->sortBy('title', 'asc', SORT_NATURAL | SORT_FLAG_CASE),
    default => $page->children()->listed()->sortBy('date', 'desc'),
  };
  $sortOptions = [
    'latest' => t('sort.latest', 'Latest'),
    'sku'    => t('sort.sku',    'Index'),
    'alpha'  => t('sort.alpha',  'A–Z'),
  ];
?>
<?php snippet('ui/breadcrumb', ['crumbs' => [
  [t('nav.home', 'Home'), site()->homePage()->url()],
  [$page->title()->value(), null],
]]) ?>
<header class="mb-6">
  <div class="usgc-sku">CISR / INDEX</div>
  <h1 class="text-xl" data-title><?= esc($page->title()) ?></h1>
</header>

<nav class="sort-bar usgc-sku" aria-label="Sort">
  <span><?= t('sort.label', 'Sort:') ?></span>
  <?php foreach ($sortOptions as $key => $label): ?>
    <a href="?sort=<?= esc($key) ?>" data-link class="<?= $sort === $key ? 'active' : '' ?>"><?= esc($label) ?></a>
  <?php endforeach ?>
</nav>

<?php if (count($list) === 0): ?>
  <p class="text-[color:var(--muted-foreground)]"><?= t('msg.no_articles', 'No articles yet.') ?></p>
<?php else: ?>
  <table class="usgc-table">
    <thead>
      <tr>
        <th class="w-24"><?= t('th.date', 'Date') ?></th>
        <th class="w-24"><?= t('th.sku', 'SKU') ?></th>
        <th><?= t('th.title', 'Title') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($list as $a): ?>
        <tr>
          <td class="whitespace-nowrap"><?= $a->date()->isNotEmpty() ? $a->date()->toDate('Y-m-d') : '—' ?></td>
          <td class="whitespace-nowrap"><?= $a->sku()->isNotEmpty() ? esc($a->sku()) : '—' ?></td>
          <td><a href="<?= $a->url() ?>" data-link><?= esc($a->title()) ?></a></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
<?php endif ?>
