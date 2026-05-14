<?php
  /** @var \Kirby\Cms\Page $page */
  $list = $page->children()->listed()->sortBy('date', 'desc');
?>
<?php snippet('ui/breadcrumb', ['crumbs' => [
  [t('nav.home', 'Home'), site()->homePage()->url()],
  [t('nav.videos', 'Videos'), null],
]]) ?>

<header class="mb-6">
  <div class="usgc-sku">CISR / VIDEOS</div>
  <h1 class="text-xl" data-title><?= esc($page->title()) ?></h1>
</header>

<?php if (count($list) === 0): ?>
  <p class="text-[color:var(--muted-foreground)]"><?= t('msg.no_videos', 'No videos yet.') ?></p>
<?php else: ?>
  <table class="usgc-table">
    <thead>
      <tr>
        <th class="w-24"><?= t('th.date', 'Date') ?></th>
        <th class="w-20"><?= t('th.kind', 'Kind') ?></th>
        <th><?= t('th.title', 'Title') ?></th>
        <th class="w-16"><?= t('th.duration', 'Dur.') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($list as $v): ?>
        <tr>
          <td><?= $v->date()->isNotEmpty() ? $v->date()->toDate('Y-m-d') : '—' ?></td>
          <td><?= esc(strtoupper($v->kind()->or('—'))) ?></td>
          <td><a href="<?= $v->url() ?>" data-link><?= esc($v->title()) ?></a></td>
          <td><?= esc($v->duration()->or('—')) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
<?php endif ?>

<p class="text-center my-10 usgc-sku">* * *</p>
