(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const panel = () => $('#panel');
  const sidebar = () => $('[data-sidebar]');
  const loadbar = () => $('#loadbar');

  function syncActive() {
    const cur = location.pathname.replace(/\/+$/, '') || '/';
    document.querySelectorAll('[data-sidebar] a[data-link]').forEach(a => {
      const path = new URL(a.href, location.origin).pathname.replace(/\/+$/, '') || '/';
      a.classList.toggle('active', path === cur);
    });
  }

  let skeletonTimer = 0;
  function showSkeleton() {
    const p = panel(); if (!p) return;
    p.innerHTML =
      '<div class="usgc-skeleton" aria-hidden="true">' +
        '<div class="sk sk-h"></div>' +
        '<div class="sk sk-line"></div>' +
        '<div class="sk sk-line"></div>' +
        '<div class="sk sk-line w-2/3"></div>' +
      '</div>';
  }

  async function swap(url, push) {
    const u = new URL(url, location.origin);
    if (u.origin !== location.origin) { location.href = url; return; }
    const sep = u.search ? '&' : '?';

    loadbar()?.classList.add('loading');
    skeletonTimer = setTimeout(showSkeleton, 120);

    try {
      const r = await fetch(u.pathname + u.search + sep + 'partial=1', {
        headers: { 'X-Partial': '1' },
        credentials: 'same-origin',
      });
      if (!r.ok) throw 0;
      const html = await r.text();
      clearTimeout(skeletonTimer);
      const p = panel();
      if (!p) { location.href = url; return; }
      p.innerHTML = html;
      if (push) history.pushState({ swap: 1 }, '', u.pathname + u.search);
      const t = p.querySelector('[data-title]');
      if (t) document.title = t.textContent.trim();
      syncActive();
      p.scrollIntoView({ block: 'start' });
      sidebar()?.classList.remove('translate-x-0');
    } catch (e) {
      clearTimeout(skeletonTimer);
      location.href = url;
    } finally {
      loadbar()?.classList.remove('loading');
    }
  }

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-sidebar-toggle]')) {
      sidebar()?.classList.toggle('translate-x-0');
      return;
    }
    if (e.target.closest('[data-theme-toggle]')) {
      const dark = !document.documentElement.classList.contains('dark');
      document.documentElement.classList.toggle('dark', dark);
      try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (_) {}
      return;
    }
    const a = e.target.closest('a[data-link]');
    if (!a) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
    e.preventDefault();
    swap(a.href, true);
  });

  addEventListener('popstate', (e) => {
    if (e.state && e.state.swap) swap(location.href, false);
  });

  // Ensure active state matches on first paint too (server already renders it, this is belt-and-suspenders).
  syncActive();
})();
