<?php
  /** @var \Kirby\Cms\Block $block */
  $level = $block->level()->or('h2');
  $allowed = ['h1','h2','h3','h4','h5','h6'];
  if (!in_array($level, $allowed, true)) { $level = 'h2'; }
?>
<<?= $level ?> id="<?= esc($block->id()) ?>" class="mt-8 mb-2">
  <?= $block->text() ?>
</<?= $level ?>>
