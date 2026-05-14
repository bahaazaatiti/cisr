<?php if (isPartialRequest()) { snippet('page/library', ['page' => $page]); return; } ?>
<?php snippet('layout-open') ?>
<?php snippet('page/library', ['page' => $page]) ?>
<?php snippet('footer') ?>
