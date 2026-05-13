<?php if (isPartialRequest()) { snippet('page/article', ['page' => $page]); return; } ?>
<?php snippet('layout-open') ?>
<?php snippet('page/article', ['page' => $page]) ?>
<?php snippet('footer') ?>
