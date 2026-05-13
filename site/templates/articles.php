<?php if (isPartialRequest()) { snippet('page/articles', ['page' => $page]); return; } ?>
<?php snippet('layout-open') ?>
<?php snippet('page/articles', ['page' => $page]) ?>
<?php snippet('footer') ?>
