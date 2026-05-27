(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const panel = () => $('#panel');
  const sidebar = () => $('[data-sidebar]');
  const loadbar = () => $('#loadbar');

  function syncActive() {
    const cur = location.pathname.replace(/\/+$/, '') || '/';
    $$('[data-sidebar] a[data-link]').forEach(a => {
      const path = new URL(a.href, location.origin).pathname.replace(/\/+$/, '') || '/';
      a.classList.toggle('active', path === cur);
    });
  }

  const SKELETON = '<div class="ui-skeleton" aria-hidden="true"><div class="sk sk-h"></div><div class="sk sk-line"></div><div class="sk sk-line"></div><div class="sk sk-line w-2/3"></div></div>';
  function showSkeleton() { const p = panel(); if (p) p.innerHTML = SKELETON; }

  let currentAbort = null;
  async function swap(url, push) {
    const u = new URL(url, location.origin);
    if (u.origin !== location.origin) { location.href = url; return; }

    if (currentAbort) currentAbort.abort();
    const abort = new AbortController();
    currentAbort = abort;
    try { window.siteP2P?.teardown?.(); } catch (_) {}

    loadbar()?.classList.add('loading');
    const skTimer = setTimeout(() => { if (!abort.signal.aborted) showSkeleton(); }, 120);

    try {
      // Same HTML serves full-page entry AND swap source; no partial endpoint.
      const r = await fetch(u.pathname + u.search, {
        credentials: 'same-origin',
        signal: abort.signal,
      });
      if (!r.ok) throw 0;
      const html = await r.text();
      if (abort.signal.aborted) return;
      clearTimeout(skTimer);
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const newPanel = doc.querySelector('#panel');
      const p = panel();
      if (!p || !newPanel) { location.href = url; return; }
      p.innerHTML = newPanel.innerHTML;
      // Refresh sidebar (labels change per language). Right aside is preserved
      // so an in-flight torrent/video keeps playing.
      const newSidebar = doc.querySelector('[data-sidebar]');
      const oldSidebar = sidebar();
      if (newSidebar && oldSidebar) oldSidebar.innerHTML = newSidebar.innerHTML;
      // innerHTML drops <script> elements; clone+replace to re-execute them.
      p.querySelectorAll('script').forEach(old => {
        const fresh = document.createElement('script');
        for (const a of old.attributes) fresh.setAttribute(a.name, a.value);
        if (old.textContent) fresh.textContent = old.textContent;
        old.replaceWith(fresh);
      });
      if (push) history.pushState({ swap: 1 }, '', u.pathname + u.search);
      // Mirror <html lang>/dir so RTL flips instantly on lang nav.
      const newHtml = doc.documentElement;
      if (newHtml.lang) document.documentElement.lang = newHtml.lang;
      if (newHtml.dir) document.documentElement.dir = newHtml.dir;
      if (doc.title) document.title = doc.title;
      const newDesc = doc.querySelector('meta[name="description"]');
      const metaDesc = document.querySelector('meta[name="description"]');
      if (newDesc && metaDesc) metaDesc.setAttribute('content', newDesc.getAttribute('content') || '');
      syncActive();
      p.scrollIntoView({ block: 'start' });
      sidebar()?.classList.remove('translate-x-0');
      const h = p.querySelector('h1');
      if (h) {
        if (!h.hasAttribute('tabindex')) h.setAttribute('tabindex', '-1');
        try { h.focus({ preventScroll: true }); } catch (_) {}
      }
    } catch (e) {
      if (abort.signal.aborted) return;
      clearTimeout(skTimer);
      location.href = url;
    } finally {
      if (currentAbort === abort) {
        currentAbort = null;
        loadbar()?.classList.remove('loading');
      }
    }
  }

  // Client-side table sort — URL stays canonical for static hosting.
  function sortTable(btn) {
    const key = btn.dataset.sort;
    const dir = btn.dataset.sortDir === 'desc' ? -1 : 1;
    const bar = btn.parentElement;
    const next = bar?.nextElementSibling;
    const table = (next && next.matches && next.matches('table[data-sortable]'))
      ? next
      : document.querySelector('#panel table[data-sortable]');
    if (!table || !table.tBodies[0]) return;
    const tbody = table.tBodies[0];
    const attr = 'sort' + key.charAt(0).toUpperCase() + key.slice(1);
    Array.from(tbody.rows)
      .sort((a, b) => {
        const av = a.dataset[attr] || '';
        const bv = b.dataset[attr] || '';
        return av < bv ? -dir : av > bv ? dir : 0;
      })
      .forEach(r => tbody.appendChild(r));
    bar?.querySelectorAll('[data-sort]').forEach(b => b.classList.toggle('active', b === btn));
  }

  // --- Video player ---
  function renderPlayer(host, data) {
    if (!host) return;
    if (!data || !data.src) {
      host.innerHTML = '<span class="ui-sku" data-player-placeholder>NO SOURCE</span>';
      host.classList.add('vid-frame-empty');
      return;
    }
    host.classList.remove('vid-frame-empty');
    const autoplay = data.src.includes('?') ? '&autoplay=1' : '?autoplay=1';
    host.innerHTML = '<iframe class="vid-frame" src="' + data.src + autoplay +
      '" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen' +
      ' referrerpolicy="strict-origin-when-cross-origin"></iframe>';
  }
  function pickVideo(btn) {
    const section = btn.closest('.ar-video') || document;
    const host = section.querySelector('#player') || $('#player');
    const status = section.querySelector('[data-ar-p2p-status]');
    $$('.vid-pick').forEach(b => b.classList.toggle('active', b === btn));
    try { window.siteP2P?.teardown?.(); } catch (_) {}
    const magnet = btn.dataset.vidMagnet;
    if (magnet) {
      host.classList.remove('vid-frame-empty');
      window.siteP2P?.open?.(magnet, 'video', host, status);
      return;
    }
    if (status) status.textContent = '';
    renderPlayer(host, { src: btn.dataset.vidSrc, title: btn.dataset.vidTitle || '' });
  }

  const KIND_LABEL = { pdf:'PDF', epub:'EPB', audio:'AUD', video:'VID', image:'IMG', archive:'ZIP', other:'OTH' };
  function kindLabel(k) { return KIND_LABEL[k] || 'OTH'; }

  function setLibMode(mode) {
    $$('[data-mode-set]').forEach(b => b.classList.toggle('active', b.dataset.modeSet === mode));
    $$('.ar-library, .drawer-panel .ar-library').forEach(s => s.dataset.mode = mode);
    $$('.lib-gui').forEach(el => el.hidden = mode !== 'gui');
    $$('.lib-list').forEach(el => el.hidden = mode !== 'list');
  }

  function escHtml(s) {
    return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  }
  function libResolve(root, path) {
    let node = root;
    for (const seg of path) {
      const child = (node.folders || []).find(f => f.slug === seg);
      if (!child) return node;
      node = child;
    }
    return node;
  }
  // Fetch /library.json once per session; cache key includes build-sha so a
  // deploy bust invalidates without a manual reload.
  const libTreeCache = new Map();
  function buildSha() {
    return document.querySelector('meta[name="build-sha"]')?.content || 'dev';
  }
  async function loadLibTree(src) {
    if (!src) return null;
    if (libTreeCache.has(src)) return libTreeCache.get(src);
    const key = 'libtree:' + buildSha() + ':' + src;
    try {
      const cached = sessionStorage.getItem(key);
      if (cached) { const t = JSON.parse(cached); libTreeCache.set(src, t); return t; }
    } catch (_) {}
    const r = await fetch(src, { credentials: 'same-origin' });
    if (!r.ok) return null;
    const tree = await r.json();
    libTreeCache.set(src, tree);
    try { sessionStorage.setItem(key, JSON.stringify(tree)); } catch (_) {}
    return tree;
  }
  function paintLibGrid(container, tree) {
    const path = (container.dataset.libPath || '').split('/').filter(Boolean);
    const node = libResolve(tree, path);
    const grid = container.querySelector('[data-lib-grid]');
    const cwd  = container.querySelector('[data-lib-cwd]');
    const up   = container.querySelector('[data-lib-up]');
    if (cwd) cwd.textContent = '/' + path.join('/');
    if (up)  up.disabled = path.length === 0;
    if (!grid) return;
    let html = '';
    (node.folders || []).forEach(f => {
      html += '<button type="button" class="lib-cell lib-cell-folder" data-lib-folder="' + escHtml(f.slug) + '" title="' + escHtml(f.name) + '">'
           +    '<span class="lib-cell-icon" aria-hidden="true">[/]</span>'
           +    '<span class="lib-cell-name">' + escHtml(f.name) + '</span>'
           +  '</button>';
    });
    (node.files || []).forEach(f => {
      const icon = '[' + kindLabel(f.kind) + ']';
      const meta = (f.size || '') + (f.date ? ' · ' + f.date : '');
      // <a> for rich items (have a detail page), <span> for lean.
      const tag = f.url ? 'a' : 'span';
      const href = f.url ? ' href="' + escHtml(f.url) + '"' : '';
      html += '<' + tag + ' class="lib-cell lib-cell-file"' + href + ' data-file'
           +    ' data-magnet="' + escHtml(f.magnet || '') + '"'
           +    ' data-kind="' + escHtml(f.kind || 'other') + '"'
           +    ' title="' + escHtml(f.name) + (meta ? ' · ' + escHtml(meta) : '') + '">'
           +    '<span class="lib-cell-icon" aria-hidden="true">' + escHtml(icon) + '</span>'
           +    '<span class="lib-cell-name">' + escHtml(f.name) + '</span>'
           +    '<span class="lib-cell-meta ui-sku">' + escHtml(f.size || '') + '</span>'
           +  '</' + tag + '>';
    });
    if (!html) {
      html = '<div class="ui-sku lib-empty-grid">' + escHtml(container.dataset.libEmpty || 'empty') + '</div>';
    }
    grid.innerHTML = html;
  }
  function paintLibList(container, tree) {
    const scope = container.closest('.ar-library, .drawer-panel') || document;
    const body  = scope.querySelector('[data-lib-list-body]');
    const empty = scope.querySelector('[data-lib-list-empty]');
    if (!body) return;
    const rows = [];
    (function walk(node, prefix, parent) {
      (node.folders || []).forEach(f => {
        const path = parent === '' ? f.slug : parent + '/' + f.slug;
        rows.push({ folder: path, parent, name: prefix + f.name + '/', size: '—', date: '—' });
        walk(f, prefix + f.name + '/', path);
      });
      (node.files || []).forEach(f => {
        rows.push({ parent, name: prefix + f.name, size: f.size || '—', date: f.date || '—',
                    url: f.url || '', magnet: f.magnet || '', kind: f.kind || 'other' });
      });
    })(tree, '', '');
    if (!rows.length) { body.innerHTML = ''; if (empty) empty.hidden = false; return; }
    if (empty) empty.hidden = true;
    let html = '';
    for (const r of rows) {
      const isFolder = !!r.folder;
      const hidden = r.parent !== '' ? ' hidden' : '';
      const attrs = ' data-parent="' + escHtml(r.parent) + '"' + (isFolder ? ' data-folder="' + escHtml(r.folder) + '"' : '');
      const icon = isFolder ? '[+]' : '[' + kindLabel(r.kind) + ']';
      html += '<tr' + (isFolder ? ' class="lib-row-folder"' : '') + attrs + hidden + '>'
           +    '<td><span' + (isFolder ? ' class="lib-toggle"' : '') + '>' + icon + '</span></td>'
           +    '<td>';
      if (isFolder) html += '<span class="lib-flat-folder">' + escHtml(r.name) + '</span>';
      else if (r.url) html += '<a href="' + escHtml(r.url) + '" data-link data-file data-magnet="' + escHtml(r.magnet) + '" data-kind="' + escHtml(r.kind) + '" title="' + escHtml(r.name) + '">' + escHtml(r.name) + '</a>';
      else html += '<span data-file data-magnet="' + escHtml(r.magnet) + '" data-kind="' + escHtml(r.kind) + '" title="' + escHtml(r.name) + '">' + escHtml(r.name) + '</span>';
      html += '</td><td class="ui-sku">' + escHtml(r.size) + '</td><td class="ui-sku">' + escHtml(r.date) + '</td></tr>';
    }
    body.innerHTML = html;
  }
  async function ensureLibContainer(container) {
    const tree = await loadLibTree(container.dataset.libTreeSrc);
    if (!tree) return;
    paintLibGrid(container, tree);
    paintLibList(container, tree);
  }
  function ensureAllLibContainers() { $$('.lib-gui[data-lib-tree-src]').forEach(ensureLibContainer); }
  async function libGo(container, segment) {
    const cur = (container.dataset.libPath || '').split('/').filter(Boolean);
    segment ? cur.push(segment) : cur.pop();
    container.dataset.libPath = cur.join('/');
    const tree = await loadLibTree(container.dataset.libTreeSrc);
    if (tree) paintLibGrid(container, tree);
  }

  let drawerCloned = false;
  function cloneIntoDrawer() {
    if (drawerCloned) return;
    const aside = $('[data-aside-right]');
    [['library', '.ar-library'], ['video', '.ar-video']].forEach(([panel, sel]) => {
      const src = aside && aside.querySelector(sel);
      const dst = $(`[data-panel="${panel}"]`);
      if (src && dst) dst.appendChild(src.cloneNode(true));
    });
    drawerCloned = true;
    ensureAllLibContainers();
  }
  function openDrawer() {
    const d = $('[data-drawer]');
    if (!d) return;
    cloneIntoDrawer();
    d.hidden = false;
    requestAnimationFrame(() => d.classList.add('open'));
    $('[data-drawer-toggle]')?.setAttribute('aria-expanded', 'true');
  }
  function closeDrawer() {
    const d = $('[data-drawer]');
    if (!d) return;
    d.classList.remove('open');
    setTimeout(() => { d.hidden = true; }, 160);
    $('[data-drawer-toggle]')?.setAttribute('aria-expanded', 'false');
  }
  function setDrawerTab(name) {
    $$('[data-tab]').forEach(b => {
      const on = b.dataset.tab === name;
      b.classList.toggle('active', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    $$('[data-panel]').forEach(p => p.hidden = p.dataset.panel !== name);
  }

  // Context menu remembers the file the menu was opened from. Magnet + kind
  // drive the p2p actions (open / download / copy magnet).
  let ctxTarget = null;
  function showCtx(x, y, target) {
    ctxTarget = target;
    const m = $('#ctxmenu'); if (!m) return;
    m.style.left = x + 'px';
    m.style.top = y + 'px';
    m.style.right = 'auto';
    m.style.bottom = 'auto';
    m.hidden = false;
  }
  function hideCtx() { const m = $('#ctxmenu'); if (m) m.hidden = true; ctxTarget = null; }
  // Resolve the p2p status element nearest the click — prefer the lib-bar's
  // own status so library downloads don't clobber the video status.
  function p2pStatusFor(el) {
    return el?.closest('[data-aside-right], .drawer-panel, #panel')?.querySelector('[data-p2p-status]') || null;
  }

  document.addEventListener('click', (e) => {
    const sort = e.target.closest('[data-sort]');
    if (sort) { sortTable(sort); return; }
    const lf = e.target.closest('[data-lib-folder]');
    if (lf) {
      const container = lf.closest('.lib-gui[data-lib-tree]');
      if (container) libGo(container, lf.dataset.libFolder);
      return;
    }
    if (e.target.closest('[data-sidebar-toggle]')) { sidebar()?.classList.toggle('translate-x-0'); return; }
    if (e.target.closest('[data-theme-toggle]')) {
      const dark = document.documentElement.classList.toggle('dark');
      try { localStorage.theme = dark ? 'dark' : 'light'; } catch (_) {}
      return;
    }
    const mode = e.target.closest('[data-mode-set]');
    if (mode) { setLibMode(mode.dataset.modeSet); return; }
    const fold = e.target.closest('tr.lib-row-folder');
    if (fold) {
      const id   = fold.dataset.folder;
      const open = fold.classList.toggle('open');
      const root = fold.closest('table') || document;
      const tog  = fold.querySelector('.lib-toggle');
      if (tog) tog.textContent = open ? '[-]' : '[+]';
      if (open) {
        root.querySelectorAll('tr[data-parent="' + CSS.escape(id) + '"]').forEach(r => r.hidden = false);
      } else {
        root.querySelectorAll('tr[data-parent="' + CSS.escape(id) + '"], tr[data-parent^="' + CSS.escape(id) + '/"]').forEach(r => {
          r.hidden = true;
          if (r.classList.contains('lib-row-folder')) {
            r.classList.remove('open');
            const t = r.querySelector('.lib-toggle');
            if (t) t.textContent = '[+]';
          }
        });
      }
      return;
    }
    const up = e.target.closest('[data-lib-up]');
    if (up) {
      const container = up.closest('.lib-gui[data-lib-tree-src]');
      if (container) libGo(container);
      return;
    }
    const vid = e.target.closest('[data-video]');
    if (vid) { pickVideo(vid); return; }
    if (e.target.closest('[data-drawer-toggle]')) {
      const d = $('[data-drawer]');
      (d && !d.hidden) ? closeDrawer() : openDrawer();
      return;
    }
    if (e.target.closest('[data-drawer-close]')) { closeDrawer(); return; }
    const tab = e.target.closest('[data-tab]');
    if (tab) { setDrawerTab(tab.dataset.tab); return; }
    const ctxBtn = e.target.closest('#ctxmenu button');
    if (ctxBtn && ctxTarget) {
      const t = ctxTarget;
      const action = ctxBtn.dataset.ctx;
      hideCtx();
      if (!t.magnet) return;
      const status = p2pStatusFor(t.el);
      if (action === 'download') {
        window.siteP2P?.download?.(t.magnet, status);
      } else if (action === 'copy') {
        window.siteP2P?.copy?.(t.magnet, status);
      } else if (action === 'open' && t.el?.href) {
        // OPEN navigates to the detail page; that page decides what to offer.
        swap(t.el.href, true);
      }
      return;
    }
    if (!e.target.closest('#ctxmenu')) hideCtx();

    // GUI cell: left-click downloads; modifier/middle falls through.
    const guiFile = e.target.closest('.lib-cell-file');
    if (guiFile) {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
      e.preventDefault();
      const mag = guiFile.dataset.magnet;
      if (mag) {
        window.siteP2P?.download?.(mag, p2pStatusFor(guiFile));
      } else if (guiFile.href) {
        swap(guiFile.href, true);
      }
      return;
    }

    const a = e.target.closest('a[data-link]');
    if (!a) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
    e.preventDefault();
    swap(a.href, true);
  });

  document.addEventListener('contextmenu', (e) => {
    // Magnet-bearing files get our 3-option menu; others fall through.
    const f = e.target.closest('[data-file][data-magnet]');
    if (!f || !f.dataset.magnet) return;
    e.preventDefault();
    showCtx(e.clientX, e.clientY, { magnet: f.dataset.magnet, kind: f.dataset.kind || 'other', el: f });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { hideCtx(); }
  });

  history.replaceState({ swap: 1 }, '', location.href);
  addEventListener('popstate', () => { swap(location.href, false); });

  syncActive();
  ensureAllLibContainers();
})();
