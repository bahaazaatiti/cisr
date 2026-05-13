<?php if (isPartialRequest()) { snippet('page/home', ['page' => $page]); return; } ?>
<?php snippet('layout-open') ?>
<?php snippet('page/home', ['page' => $page]) ?>
<?php snippet('footer') ?>
