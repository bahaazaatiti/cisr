<?php
  $library = page('library');
  $videos  = page('videos');

  // Build a JSON tree of the library for the GUI mode (client-side file explorer).
  $buildTree = function ($node) use (&$buildTree) {
    $folders = [];
    foreach ($node->children()->listed()->sortBy('title', 'asc', SORT_NATURAL | SORT_FLAG_CASE) as $f) {
      $folders[] = $buildTree($f);
    }
    $files = [];
    foreach ($node->files()->sortBy('filename', 'asc', SORT_NATURAL | SORT_FLAG_CASE) as $file) {
      $files[] = [
        'name' => $file->filename(),
        'url'  => (string) $file->url(),
        'size' => (string) ($file->niceSize() ?: ''),
        'date' => (string) ($file->modified('Y-m-d') ?: ''),
      ];
    }
    return [
      'name'    => (string) ($node->title()->value() ?: $node->slug()),
      'slug'    => $node->slug(),
      'folders' => $folders,
      'files'   => $files,
    ];
  };
  $libraryJson = $library ? json_encode($buildTree($library), JSON_UNESCAPED_UNICODE) : 'null';

  // Flat rows for LIST mode (recursive, depth-prefixed name)
  $flatRows = [];
  $flatten = function ($node, $prefix = '') use (&$flatten, &$flatRows) {
    foreach ($node->children()->listed() as $f) {
      $title = (string) ($f->title()->value() ?: $f->slug());
      $flatRows[] = [
        'icon' => '[/]',
        'name' => $prefix . $title . '/',
        'size' => '—',
        'date' => (string) ($f->modified('Y-m-d') ?: '—'),
        'href' => (string) $f->url(),
        'type' => 'folder',
      ];
      $flatten($f, $prefix . $title . '/');
    }
    foreach ($node->files() as $file) {
      $flatRows[] = [
        'icon' => '[ ]',
        'name' => $prefix . $file->filename(),
        'size' => (string) ($file->niceSize() ?: '—'),
        'date' => (string) ($file->modified('Y-m-d') ?: '—'),
        'href' => (string) $file->url(),
        'type' => 'file',
      ];
    }
  };
  if ($library) { $flatten($library); }
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
      <div class="lib-gui" data-lib-tree='<?= esc($libraryJson, 'attr') ?>' data-lib-path="">
        <div class="lib-bar">
          <button type="button" class="lib-up" data-lib-up disabled title="<?= t('media.up', 'Up') ?>"><span aria-hidden="true">↑</span></button>
          <span class="lib-cwd" data-lib-cwd>/</span>
        </div>
        <div class="lib-grid" data-lib-grid></div>
      </div>
      <div class="lib-list" hidden>
        <?php if (empty($flatRows)): ?>
          <div class="usgc-sku"><?= t('msg.empty_folder', 'empty') ?></div>
        <?php else: ?>
          <table class="lib-table">
            <thead><tr>
              <th class="w-6"></th>
              <th><?= t('th.name', 'NAME') ?></th>
              <th class="w-12"><?= t('th.size', 'SIZE') ?></th>
              <th class="w-20"><?= t('th.modified', 'MODIFIED') ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($flatRows as $r): ?>
              <tr>
                <td><?= esc($r['icon']) ?></td>
                <td><?php if ($r['type'] === 'file'): ?>
                  <a href="<?= esc($r['href']) ?>" download data-file title="<?= esc($r['name']) ?>"><?= esc($r['name']) ?></a>
                <?php else: ?>
                  <span class="lib-flat-folder"><?= esc($r['name']) ?></span>
                <?php endif ?></td>
                <td class="usgc-sku"><?= esc($r['size']) ?></td>
                <td class="usgc-sku"><?= esc($r['date']) ?></td>
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
      <div class="ar-tools">
        <button data-video-fullscreen class="ar-mode" type="button" aria-label="<?= t('media.fullscreen', 'Fullscreen') ?>" title="<?= t('media.fullscreen', 'Fullscreen') ?>">⧉</button>
      </div>
    </header>
    <div class="vid-stage">
      <div id="player" class="vid-frame vid-frame-empty">
        <span class="usgc-sku" data-player-placeholder><?= t('msg.pick_video', 'PICK A VIDEO') ?></span>
      </div>
    </div>
    <ul class="vid-list">
      <?php if ($videos): foreach ($videos->children()->listed()->sortBy('date', 'desc') as $v): ?>
        <?php
          $vsrc = $v->videoEmbedUrl();
          if (!$vsrc) continue;
        ?>
        <li>
          <button type="button" class="vid-pick"
            data-video
            data-vid-src="<?= esc($vsrc) ?>"
            data-vid-title="<?= esc($v->title()) ?>">
            <span class="vid-pick-icon" aria-hidden="true">▷</span>
            <span class="vid-pick-title"><?= esc($v->title()) ?></span>
            <?php if ($v->duration()->isNotEmpty()): ?>
              <span class="usgc-sku vid-pick-dur"><?= esc($v->duration()) ?></span>
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
    <button type="button" data-drawer-close class="drawer-x" aria-label="Close">✕</button>
  </div>
  <div class="drawer-panels">
    <div data-panel="library" class="drawer-panel"></div>
    <div data-panel="video"   class="drawer-panel" hidden></div>
  </div>
</div>

<dialog id="vid-fs" class="vid-fs" aria-label="<?= t('media.video', 'Video') ?>">
  <div class="vid-fs-bar">
    <span class="usgc-sku" data-fs-title><?= t('media.video', 'VIDEO') ?></span>
    <button type="button" data-fs-close aria-label="Close">✕</button>
  </div>
  <div class="vid-fs-stage"></div>
</dialog>

<div id="ctxmenu" class="ctxmenu" hidden>
  <button type="button" data-ctx="download"><?= t('media.download', 'DOWNLOAD') ?></button>
  <button type="button" data-ctx="copy"><?= t('media.copylink', 'COPY LINK') ?></button>
</div>
