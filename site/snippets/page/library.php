<?php
  /** @var \Kirby\Cms\Page $page */
  $isRoot = $page->slug() === 'library';
?>
<?php snippet('ui/breadcrumb', ['crumbs' => array_filter([
  [t('nav.home', 'Home'), site()->homePage()->url()],
  [t('nav.library', 'Library'), $isRoot ? null : page('library')?->url()],
  $isRoot ? null : [$page->title()->value(), null],
])]) ?>

<header class="mb-6">
  <div class="usgc-sku">CISR / LIBRARY</div>
  <h1 class="text-xl" data-title="<?= esc($page->fullTitle()) ?>"><?= esc($page->title()) ?></h1>
  <?php if ($page->description()->isNotEmpty()): ?>
    <p class="text-sm text-muted-foreground mt-2"><?= esc($page->description()) ?></p>
  <?php endif ?>
</header>

<?php $folders = $page->children()->listed(); ?>
<?php $files = $page->files(); ?>

<?php if (count($folders) === 0 && count($files) === 0): ?>
  <p class="text-muted-foreground"><?= t('msg.empty_folder', 'This folder is empty.') ?></p>
<?php else: ?>
  <table class="usgc-table">
    <thead>
      <tr>
        <th class="w-8"></th>
        <th><?= t('th.name', 'Name') ?></th>
        <th class="w-24"><?= t('th.size', 'Size') ?></th>
        <th class="w-28"><?= t('th.modified', 'Modified') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$isRoot && $page->parent()): ?>
        <tr>
          <td>↑</td>
          <td colspan="3"><a href="<?= $page->parent()->url() ?>" data-link>..</a></td>
        </tr>
      <?php endif ?>
      <?php foreach ($folders as $f): ?>
        <tr>
          <td>[+]</td>
          <td><a href="<?= $f->url() ?>" data-link><?= esc($f->title()) ?>/</a></td>
          <td>—</td>
          <td><?= $f->modified('Y-m-d') ?></td>
        </tr>
      <?php endforeach ?>
      <?php foreach ($files as $f): ?>
        <tr>
          <td>[<?= cisr_file_kind($f) ?>]</td>
          <td><a href="<?= $f->url() ?>" download data-file><?= esc($f->filename()) ?></a></td>
          <td><?= $f->niceSize() ?></td>
          <td><?= $f->modified('Y-m-d') ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
<?php endif ?>

<p class="text-center my-10 usgc-sku">* * *</p>
