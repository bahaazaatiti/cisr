<?php /** @var \Kirby\Cms\Block $block */ ?>
<blockquote class="my-4 border-s border-[color:var(--border)] ps-4 text-[color:var(--muted-foreground)]">
  <?= $block->text() ?>
  <?php if ($block->citation()->isNotEmpty()): ?>
    <footer class="mt-2 usgc-sku">— <?= esc($block->citation()) ?></footer>
  <?php endif ?>
</blockquote>
