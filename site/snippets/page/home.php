<?php
  /** @var \Kirby\Cms\Page $page */
  $articles = page('articles');
  $allCount = $articles ? $articles->children()->listed()->count() : 0;
  $list = $articles
    ? $articles->children()->listed()->sortBy('date', 'desc')->limit(5)
    : new \Kirby\Cms\Pages();
?>
<header class="mb-6">
  <div class="usgc-sku"><?= esc(strtoupper($page->kirby()->language()->code())) ?> / HOME</div>
  <h1 class="text-xl" data-title><?= esc($page->title()) ?></h1>
</header>

<figure class="home-art home-art-signal" aria-hidden="true">
  <img src="<?= url('assets/img/signal.svg') ?>" alt="">
</figure>

<?php if ($page->intro()->isNotEmpty()): ?>
  <section class="prose-usgc mb-8">
    <?= $page->intro()->kt() ?>
  </section>
<?php endif ?>

<figure class="home-art home-art-place" aria-hidden="true">
  <img src="<?= url('assets/img/place.svg') ?>" alt="">
</figure>

<?php $featured = $page->featured()->toPages(); ?>
<?php if (count($featured)): ?>
  <section class="featured mb-8">
    <div class="flex items-baseline justify-between mb-2">
      <span class="usgc-badge" style="color:var(--accent)">★ <?= t('section.featured', 'FEATURED') ?></span>
      <span class="usgc-sku"><?= count($featured) ?> <?= t('label.entries', 'entries') ?></span>
    </div>
    <ul class="featured-list">
      <?php foreach ($featured as $f): ?>
        <li>
          <a href="<?= $f->url() ?>" data-link>
            <h3><?= esc($f->title()) ?></h3>
            <?php if ($f->summary()->isNotEmpty()): ?>
              <p class="text-sm text-[color:var(--muted-foreground)] mt-1"><?= esc($f->summary()) ?></p>
            <?php endif ?>
          </a>
        </li>
      <?php endforeach ?>
    </ul>
  </section>
<?php endif ?>

<div class="usgc-rule mb-6" role="separator"></div>

<section>
  <h2 class="text-sm mb-2"><?= t('section.latest', 'Latest') ?></h2>

  <?php if (count($list) === 0): ?>
    <p class="text-[color:var(--muted-foreground)]"><?= t('msg.no_articles', 'No articles yet.') ?></p>
  <?php else: ?>
    <table class="usgc-table">
      <thead>
        <tr>
          <th class="w-24"><?= t('th.date', 'Date') ?></th>
          <th class="w-24"><?= t('th.sku', 'SKU') ?></th>
          <th><?= t('th.title', 'Title') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $a): ?>
          <tr>
            <td class="whitespace-nowrap"><?= $a->date()->isNotEmpty() ? $a->date()->toDate('Y-m-d') : '—' ?></td>
            <td class="whitespace-nowrap"><?= $a->sku()->isNotEmpty() ? esc($a->sku()) : '—' ?></td>
            <td><a href="<?= $a->url() ?>" data-link><?= esc($a->title()) ?></a></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <?php if ($allCount > count($list) && $articles): ?>
      <p class="mt-3 usgc-sku"><a href="<?= $articles->url() ?>" data-link><?= t('label.view_all', 'View all') ?> →</a></p>
    <?php endif ?>
  <?php endif ?>
</section>

<p class="text-center my-10 usgc-sku">* * *</p>
