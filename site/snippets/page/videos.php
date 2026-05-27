<?php
  /** @var \Kirby\Cms\Page $page */
  $list = $page->children()->listed()->sortBy('date', 'desc');
?>
<?php snippet('ui/breadcrumb', ['crumbs' => [
  [t('nav.home', 'Home'), site()->homePage()->url()],
  [t('nav.videos', 'Videos'), null],
]]) ?>

<header class="mb-6">
  <div class="ui-sku"><?= esc(option('brand.sku', site()->title())) ?> / <?= esc(strtoupper(t('nav.videos', 'VIDEOS'))) ?></div>
  <h1 class="text-xl"><?= esc($page->title()) ?></h1>
</header>

<?php if (count($list) === 0): ?>
  <p class="text-muted-foreground"><?= t('msg.no_videos', 'No videos yet.') ?></p>
<?php else: ?>
  <table class="ui-table">
    <thead>
      <tr>
        <th class="w-24"><?= t('th.date', 'Date') ?></th>
        <th class="w-20"><?= t('th.kind', 'Kind') ?></th>
        <th><?= t('th.title', 'Title') ?></th>
        <th class="w-16"><?= t('th.duration', 'Dur.') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($list as $v):
        $src = $v->hasMagnetSource() ? 'WT' : ($v->hasYouTubeSource() ? 'YT' : '—');
        $rich = $v->isRich();
      ?>
        <tr>
          <td><?= $v->date()->toDate('Y-m-d') ?: '—' ?></td>
          <td><?= esc($src) ?></td>
          <td>
            <?php if ($rich): ?>
              <a href="<?= $v->url() ?>" data-link><?= esc($v->title()) ?></a>
            <?php else: ?>
              <span><?= esc($v->title()) ?></span>
            <?php endif ?>
          </td>
          <td><?= esc($v->duration()->or('—')) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
<?php endif ?>

<p class="text-center my-10 ui-sku">* * *</p>
