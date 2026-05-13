<?php
  /** @var \Kirby\Cms\Page $page */
  $list = $page->children()->listed()->sortBy('date', 'desc');
?>
<?php snippet('ui/breadcrumb', ['crumbs' => [
  [t('nav.home', 'Home'), site()->homePage()->url()],
  [$page->title()->value(), null],
]]) ?>
<header class="mb-6">
  <div class="usgc-sku">CISR / INDEX</div>
  <h1 class="text-xl" data-title><?= esc($page->title()) ?></h1>
</header>

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
