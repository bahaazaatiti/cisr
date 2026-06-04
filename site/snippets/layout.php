<?php
  /** @var \Kirby\Cms\Page $page */
  // A page's template == its content filename. intendedTemplate() returns it even
  // though every content page now renders through default.php, so this one layout
  // dispatches to the right page/<template> content snippet.
  $name = $page->intendedTemplate()->name();
?>
<?php snippet('layout-open') ?>
<?php snippet('page/' . $name, ['page' => $page]) ?>
<?php snippet('footer') ?>
