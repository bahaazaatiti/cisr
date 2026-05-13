<?php
  /** @var \Kirby\Cms\Block $block */
  $alt = $block->alt()->or('');
  $caption = $block->caption();
  if ($block->location() == 'web') {
    $src = $block->src();
  } else {
    $image = $block->image()->toFile();
    $src = $image?->url() ?? '';
  }
?>
<figure class="my-6">
  <?php if ($src): ?>
    <img src="<?= esc($src) ?>" alt="<?= esc($alt) ?>" loading="lazy" decoding="async" class="border border-[color:var(--border)] max-w-full h-auto block">
  <?php endif ?>
  <?php if ($caption->isNotEmpty()): ?>
    <figcaption class="mt-2 usgc-sku"><?= $caption ?></figcaption>
  <?php endif ?>
</figure>
