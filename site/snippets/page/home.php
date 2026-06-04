<?php
  /** @var \Kirby\Cms\Page $page */
  $articles = page('articles');
  $list = collection('articles')->limit(5);
  $allCount = collection('articles')->count();
?>
<header class="mb-6">
  <div class="ui-sku"><?= esc(strtoupper($page->kirby()->language()->code())) ?> / HOME</div>
  <h1 class="text-xl"><?= esc($page->title()) ?></h1>
</header>

<?php /* Live broadcast hero — renders only when a broadcast is on. */ ?>
<?php snippet('page/broadcast-hero') ?>

<?php foreach ($page->site()->body()->toBlocks() as $block): ?>
  <?php if ($block->type() === 'image'):
    $image = $block->image()->toFile();
    if (!$image) continue;
    $alt = (string) $block->alt();
  ?>
    <figure class="home-art">
      <img src="<?= esc($image->url()) ?>" alt="<?= esc($alt) ?>"
           <?php if ($image->width() && $image->height()): ?>width="<?= $image->width() ?>" height="<?= $image->height() ?>"<?php endif ?>
           loading="lazy" decoding="async"
           <?= $alt === '' ? 'aria-hidden="true"' : '' ?>>
    </figure>
  <?php elseif ($block->type() === 'text'): ?>
    <section class="ui-prose mb-8"><?= $block->text() ?></section>
  <?php endif ?>
<?php endforeach ?>

<?php $featured = $page->site()->featured()->toPages(); ?>
<?php if (count($featured)): ?>
  <section class="featured mb-8">
    <div class="flex items-baseline justify-between mb-2">
      <h2 class="ui-badge text-accent"><span aria-hidden="true">★</span> <?= t('section.featured', 'FEATURED') ?></h2>
      <span class="ui-sku"><?= count($featured) ?> <?= t('label.entries', 'entries') ?></span>
    </div>
    <ul class="featured-list">
      <?php foreach ($featured as $f): ?>
        <li>
          <a href="<?= $f->url() ?>" data-link>
            <h3><?= esc($f->title()) ?></h3>
            <?php if ($f->summary()->isNotEmpty()): ?>
              <p class="text-sm text-muted-foreground mt-1"><?= esc($f->summary()) ?></p>
            <?php endif ?>
          </a>
        </li>
      <?php endforeach ?>
    </ul>
  </section>
<?php endif ?>

<div class="ui-rule mb-6" role="separator"></div>

<section>
  <h2 class="text-sm mb-2"><?= t('section.latest', 'Latest') ?></h2>

  <?php if (count($list) === 0): ?>
    <p class="text-muted-foreground"><?= t('msg.no_articles', 'No articles yet.') ?></p>
  <?php else: ?>
    <table class="ui-table">
      <thead>
        <tr>
          <th class="w-32"><?= t('th.date', 'Date') ?></th>
          <th class="w-28"><?= t('th.sku', 'SKU') ?></th>
          <th><?= t('th.title', 'Title') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $a): ?>
          <tr>
            <td class="whitespace-nowrap"><?= $a->date()->toDate('Y-m-d') ?: '—' ?></td>
            <td class="whitespace-nowrap"><?= esc($a->sku()->or('—')) ?></td>
            <td><a href="<?= $a->url() ?>" data-link><?= esc($a->title()) ?></a></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <?php if ($allCount > count($list) && $articles): ?>
      <p class="mt-3 ui-sku"><a href="<?= $articles->url() ?>" data-link><?= t('label.view_all', 'View all') ?> →</a></p>
    <?php endif ?>
  <?php endif ?>
</section>

<p class="text-center my-10 ui-sku">* * *</p>

<?php /* Colophon: open (newcomers) or resume (editors) the full editing env in
         a browser Codespace — quickstart=1 does both. External: no data-link. */ ?>
<p class="text-center mb-10 ui-sku">
  <a href="https://codespaces.new/bahaazaatiti/cisr?quickstart=1"
     target="_blank" rel="noopener"><?= t('label.edit_codespaces', 'Edit this site') ?> →</a>
</p>
