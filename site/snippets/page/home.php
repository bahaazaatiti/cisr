<?php
  /** @var \Kirby\Cms\Page $page */
  $articles = page('articles');
  $list = $articles
    ? $articles->children()->listed()->sortBy('date', 'desc')
    : new \Kirby\Cms\Pages();
?>
<header class="mb-6">
  <div class="usgc-sku"><?= esc(strtoupper($page->kirby()->language()->code())) ?> / HOME</div>
  <h1 class="text-xl" data-title><?= esc($page->title()) ?></h1>
</header>

<?php if ($page->intro()->isNotEmpty()): ?>
  <section class="prose-usgc mb-8">
    <?= $page->intro()->kt() ?>
  </section>
<?php endif ?>

<div class="usgc-rule mb-6" role="separator"></div>

<section>
  <div class="flex items-baseline justify-between mb-2">
    <h2 class="text-sm"><?= t('section.articles', 'Articles') ?></h2>
    <span class="usgc-sku"><?= count($list) ?> <?= t('label.entries', 'entries') ?></span>
  </div>

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
  <?php endif ?>
</section>

<p class="text-center my-10 usgc-sku">* * *</p>
