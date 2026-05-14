<?php /** @var \Kirby\Cms\Block $block */ ?>
<blockquote class="my-4">
  <?= $block->text() ?>
  <?php if ($block->citation()->isNotEmpty()): ?>
    <footer class="mt-2 usgc-sku">— <?= esc($block->citation()) ?></footer>
  <?php endif ?>
</blockquote>
