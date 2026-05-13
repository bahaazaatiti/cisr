<?php if (isPartialRequest()) { snippet('page/default', ['page' => $page]); return; } ?>
<?php snippet('layout-open') ?>
<?php snippet('page/default', ['page' => $page]) ?>
<?php snippet('footer') ?>
