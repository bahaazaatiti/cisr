<?php if (isPartialRequest()) { snippet('page/video', ['page' => $page]); return; } ?>
<?php snippet('layout-open') ?>
<?php snippet('page/video', ['page' => $page]) ?>
<?php snippet('footer') ?>
