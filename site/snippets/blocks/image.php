<?php
  /** @var \Kirby\Cms\Block $block */
  $alt = $block->alt()->or('');
  $caption = $block->caption();
  $w = null;
  $h = null;
  if ($block->location() == 'web') {
    $src = (string) $block->src();
  } else {
    $image = $block->image()->toFile();
    $src = $image?->url() ?? '';
    if ($image) {
      $w = $image->width();
      $h = $image->height();
    }
  }
?>
<figure class="my-6">
  <?php if ($src): ?>
    <img
      src="<?= esc($src) ?>"
      alt="<?= esc($alt) ?>"
      <?php if ($w && $h): ?>width="<?= $w ?>" height="<?= $h ?>"<?php endif ?>
      loading="lazy" decoding="async">
  <?php endif ?>
  <?php if ($caption->isNotEmpty()): ?>
    <figcaption class="mt-2 usgc-sku"><?= $caption ?></figcaption>
  <?php endif ?>
</figure>
