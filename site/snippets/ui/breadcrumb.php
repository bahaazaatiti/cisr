<?php
  /** @var array $crumbs  [ [label, url|null], ... ] */
  $crumbs ??= [];
  if (empty($crumbs)) return;
?>
<nav class="breadcrumb" aria-label="Breadcrumb">
  <?php $last = count($crumbs) - 1; foreach ($crumbs as $i => $c): ?>
    <?php [$label, $href] = $c + [null, null]; ?>
    <?php if ($href && $i !== $last): ?>
      <a href="<?= esc($href) ?>" data-link><?= esc($label) ?></a>
    <?php else: ?>
      <span><?= esc($label) ?></span>
    <?php endif ?>
    <?php if ($i !== $last): ?><span class="breadcrumb-sep" aria-hidden="true">›</span><?php endif ?>
  <?php endforeach ?>
</nav>
