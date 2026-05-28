<?php
  /** @var \Kirby\Cms\Page $page */
?>
<header class="mb-6">
  <h1 class="text-xl"><?= esc($page->title()) ?></h1>
</header>
<div class="ui-prose">
  <?php if ($page->text()->isNotEmpty()): ?>
    <?= $page->text()->kt() ?>
  <?php endif ?>
</div>
