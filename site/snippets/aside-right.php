<?php
  $library = page('library');
  $videos  = page('videos');

  // Build a JSON tree of the library for the GUI mode (client-side file explorer).
  // Folders = nested library pages; items = library-item leaf pages with magnets.
  $buildTree = function ($node) use (&$buildTree) {
    $folders = [];
    $files   = [];
    foreach ($node->children()->listed()->sortBy('title', 'asc', SORT_NATURAL | SORT_FLAG_CASE) as $c) {
      $tpl = $c->intendedTemplate()->name();
      if ($tpl === 'library') {
        $folders[] = $buildTree($c);
      } elseif ($tpl === 'library-item') {
        $kind = (string) $c->kind() ?: 'other';
        $files[] = [
          'name' => (string) ($c->title()->value() ?: $c->slug()),
          'url'  => (string) $c->url(),
          'size' => (string) ($c->size_human()->or('')),
          'date' => $c->added()->isNotEmpty() ? $c->added()->toDate('Y-m-d') : '',
          'kind' => $kind,
          'magnet' => (string) $c->magnet(),
        ];
      }
    }
    return [
      'name'    => (string) ($node->title()->value() ?: $node->slug()),
      'slug'    => $node->slug(),
      'folders' => $folders,
      'files'   => $files,
    ];
  };
  // JSON_UNESCAPED_SLASHES is critical: the SSG plugin string-replaces a
  // `https://jr-ssg-base-url` placeholder in the final HTML to inject the
  // deploy base. PHP's default json_encode escapes `/` to `\/`, which would
  // hide the placeholder from that replace and leak it into the browser.
  $libraryJson = $library ? json_encode($buildTree($library), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null';

  // Flat rows for LIST mode (recursive, depth-prefixed name, collapsible via parent path)
  $flatRows = [];
  $flatten = function ($node, $prefix = '', $parent = '') use (&$flatten, &$flatRows) {
    foreach ($node->children()->listed() as $c) {
      $tpl   = $c->intendedTemplate()->name();
      $title = (string) ($c->title()->value() ?: $c->slug());
      if ($tpl === 'library') {
        $path = ($parent === '' ? '' : $parent . '/') . $c->slug();
        $flatRows[] = [
          'type'   => 'folder',
          'folder' => $path,
          'parent' => $parent,
          'name'   => $prefix . $title . '/',
          'size'   => '—',
          'date'   => (string) ($c->modified('Y-m-d') ?: '—'),
        ];
        $flatten($c, $prefix . $title . '/', $path);
      } elseif ($tpl === 'library-item') {
        $kind = (string) $c->kind() ?: 'other';
        $flatRows[] = [
          'type'   => 'item',
          'parent' => $parent,
          'name'   => $prefix . $title,
          'size'   => (string) ($c->size_human()->or('—')),
          'date'   => $c->added()->isNotEmpty() ? $c->added()->toDate('Y-m-d') : '—',
          'href'   => $c->isRich() ? (string) $c->url() : '',
          'kind'   => $kind,
          'magnet' => (string) $c->magnet(),
        ];
      }
    }
  };
  if ($library) { $flatten($library); }

  // 3-letter PHP-side kind labels (mirror JS map in app.js).
  $kindLabel = function ($k) {
    static $m = [
      'pdf' => 'PDF', 'epub' => 'EPB', 'audio' => 'AUD', 'video' => 'VID',
      'image' => 'IMG', 'archive' => 'ZIP', 'other' => 'OTH',
    ];
    return $m[$k] ?? 'OTH';
  };
?>
<aside class="aside-right" data-aside-right>
  <section class="ar-half ar-library" data-mode="gui">
    <header class="ar-head">
      <span class="ar-title"><?= t('media.library', 'LIBRARY') ?></span>
      <div class="ar-tools">
        <button data-mode-set="gui"  class="ar-mode active" type="button" aria-label="<?= t('media.gui',  'GUI')  ?>" title="<?= t('media.gui',  'GUI')  ?>">▦</button>
        <button data-mode-set="list" class="ar-mode"        type="button" aria-label="<?= t('media.list', 'LIST') ?>" title="<?= t('media.list', 'LIST') ?>">≣</button>
      </div>
    </header>
    <div class="ar-body">
      <?php
        // Note on attribute quoting: we use double quotes + esc(default html
        // mode) — NOT esc('attr'). The 'attr' variant escapes "/" and ":" to
        // HTML entities, which hides the SSG plugin's `https://jr-ssg-base-url`
        // placeholder from its raw str_replace pass and causes broken URLs.
      ?>
      <div class="lib-gui" data-lib-tree="<?= esc($libraryJson) ?>" data-lib-path="">
        <div class="lib-bar">
          <button type="button" class="lib-up" data-lib-up disabled title="<?= t('media.up', 'Up') ?>"><span aria-hidden="true">↑</span></button>
          <span class="lib-cwd" data-lib-cwd>/</span>
          <span class="ui-sku lib-status" data-p2p-status></span>
        </div>
        <div class="lib-grid" data-lib-grid></div>
      </div>
      <div class="lib-list" hidden>
        <?php if (empty($flatRows)): ?>
          <div class="ui-sku"><?= t('msg.empty_folder', 'empty') ?></div>
        <?php else: ?>
          <table class="lib-table">
            <thead><tr>
              <th class="w-6"></th>
              <th><?= t('th.name', 'NAME') ?></th>
              <th class="w-12"><?= t('th.size', 'SIZE') ?></th>
              <th class="w-20"><?= t('th.added', 'ADDED') ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($flatRows as $r): ?>
              <?php
                $isFolder = $r['type'] === 'folder';
                $hidden   = $r['parent'] !== '';
                $attrs    = ' data-parent="' . esc($r['parent'], 'attr') . '"';
                if ($isFolder) $attrs .= ' data-folder="' . esc($r['folder'], 'attr') . '"';
                $icon     = $isFolder ? '[+]' : '[' . $kindLabel($r['kind'] ?? 'other') . ']';
              ?>
              <tr<?= $isFolder ? ' class="lib-row-folder"' : '' ?><?= $attrs ?><?= $hidden ? ' hidden' : '' ?>>
                <td><span<?= $isFolder ? ' class="lib-toggle"' : '' ?>><?= $icon ?></span></td>
                <td>
                  <?php if ($isFolder): ?>
                    <span class="lib-flat-folder"><?= esc($r['name']) ?></span>
                  <?php elseif (!empty($r['href'])): ?>
                    <a href="<?= esc($r['href']) ?>" data-link data-file
                       data-magnet="<?= esc($r['magnet'] ?? '', 'attr') ?>"
                       data-kind="<?= esc($r['kind'] ?? 'other', 'attr') ?>"
                       title="<?= esc($r['name']) ?>"><?= esc($r['name']) ?></a>
                  <?php else: ?>
                    <span data-file
                          data-magnet="<?= esc($r['magnet'] ?? '', 'attr') ?>"
                          data-kind="<?= esc($r['kind'] ?? 'other', 'attr') ?>"
                          title="<?= esc($r['name']) ?>"><?= esc($r['name']) ?></span>
                  <?php endif ?>
                </td>
                <td class="ui-sku"><?= esc($r['size']) ?></td>
                <td class="ui-sku"><?= esc($r['date']) ?></td>
              </tr>
            <?php endforeach ?>
            </tbody>
          </table>
        <?php endif ?>
      </div>
    </div>
  </section>

  <section class="ar-half ar-video">
    <header class="ar-head">
      <span class="ar-title"><?= t('media.video', 'VIDEO') ?></span>
    </header>
    <div class="vid-stage">
      <div id="player" class="vid-frame vid-frame-empty">
        <span class="ui-sku" data-player-placeholder><?= t('msg.pick_video', 'PICK A VIDEO') ?></span>
      </div>
    </div>
    <div class="ui-sku ar-p2p-status" data-ar-p2p-status></div>
    <ul class="vid-list">
      <?php if ($videos): foreach ($videos->children()->listed()->sortBy('date', 'desc') as $v):
        $isYT     = $v->hasYouTubeSource();
        $isMagnet = $v->hasMagnetSource();
        if (!$isYT && !$isMagnet) continue;
        $vsrc     = $isYT ? $v->videoEmbedUrl() : null;
        $vmagnet  = $isMagnet ? (string) $v->magnet() : null;
        $badge    = $isMagnet ? 'WT' : 'YT';
      ?>
        <li>
          <button type="button" class="vid-pick"
            data-video
            <?php if ($vsrc): ?>data-vid-src="<?= esc($vsrc) ?>"<?php endif ?>
            <?php if ($vmagnet): ?>data-vid-magnet="<?= esc($vmagnet) ?>"<?php endif ?>
            data-vid-title="<?= esc($v->title()) ?>">
            <span class="vid-pick-icon" aria-hidden="true">▷</span>
            <span class="vid-pick-title"><?= esc($v->title()) ?></span>
            <span class="ui-sku vid-pick-badge"><?= $badge ?></span>
            <?php if ($v->duration()->isNotEmpty()): ?>
              <span class="ui-sku vid-pick-dur"><?= esc($v->duration()) ?></span>
            <?php endif ?>
          </button>
        </li>
      <?php endforeach; endif ?>
    </ul>
  </section>
</aside>

<button class="drawer-toggle" data-drawer-toggle type="button" aria-label="<?= t('media.media', 'Open media') ?>">
  <span aria-hidden="true">▲</span> <?= t('media.media', 'MEDIA') ?>
</button>

<div class="drawer" data-drawer hidden>
  <div class="drawer-tabs" role="tablist">
    <button type="button" data-tab="library" class="active" role="tab" aria-selected="true"><?= t('media.library', 'LIBRARY') ?></button>
    <button type="button" data-tab="video" role="tab" aria-selected="false"><?= t('media.video', 'VIDEO') ?></button>
    <button type="button" data-drawer-close class="drawer-x" aria-label="<?= t('ui.close', 'Close') ?>">✕</button>
  </div>
  <div class="drawer-panels">
    <div data-panel="library" class="drawer-panel"></div>
    <div data-panel="video"   class="drawer-panel" hidden></div>
  </div>
</div>

<div id="ctxmenu" class="ctxmenu" hidden>
  <button type="button" data-ctx="open"><?= t('media.open', 'OPEN') ?></button>
  <button type="button" data-ctx="download"><?= t('media.download', 'DOWNLOAD') ?></button>
  <button type="button" data-ctx="copy"><?= t('media.copylink', 'COPY MAGNET') ?></button>
</div>
