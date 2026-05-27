<?php
  /** @var \Kirby\Cms\Page $page */
  /** @var string $name */
?>
<?php snippet('layout-open') ?>
<?php snippet('page/' . $name, ['page' => $page]) ?>
<?php snippet('footer') ?>
