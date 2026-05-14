<?php
  /** @var \Kirby\Cms\Page $page */
  if (!kirby()->user()) return;
  $status = $page->status();
  if (!in_array($status, ['draft', 'unlisted', 'listed'], true)) return;
?>
<span class="usgc-badge" data-status="<?= esc($status) ?>"><?= esc(strtoupper($status)) ?></span>
