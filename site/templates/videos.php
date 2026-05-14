<?php if (isPartialRequest()) { snippet('page/videos', ['page' => $page]); return; } ?>
<?php snippet('layout-open') ?>
<?php snippet('page/videos', ['page' => $page]) ?>
<?php snippet('footer') ?>
