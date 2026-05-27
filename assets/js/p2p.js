(() => {
  if (window.cisrP2P) return;

  let client = null;
  let torrent = null;
  let statusTimer = null;
  let activeStatusEl = null;
  let wtPromise = null;
  let swPromise = null;
  let serverStarted = false;

  function vendorUrl() {
    const s = document.querySelector('script[data-vendor]');
    if (s && s.dataset.vendor) return s.dataset.vendor;
    return '/assets/js/vendor/webtorrent.min.js';
  }

  function swUrl() {
    // SW must live at site root so scope:'/' works without a custom
    // Service-Worker-Allowed header (GitHub Pages can't set one).
    // router.php special-cases /sw.min.js in local dev to add the header.
    return '/sw.min.js';
  }

  function loadWebTorrent() {
    if (window.WebTorrent) return Promise.resolve(window.WebTorrent);
    if (wtPromise) return wtPromise;
    wtPromise = import(vendorUrl())
      .then(mod => { window.WebTorrent = mod.default || mod.WebTorrent; return window.WebTorrent; })
      .catch(err => { wtPromise = null; throw err; });
    return wtPromise;
  }

  // Register the WebTorrent service worker so file.streamURL can be used for
  // progressive playback (no need to wait for the whole file to download).
  function registerSW() {
    if (swPromise) return swPromise;
    if (!('serviceWorker' in navigator)) {
      swPromise = Promise.resolve(null);
      return swPromise;
    }
    swPromise = navigator.serviceWorker.register(swUrl(), { scope: '/' })
      .then(reg => {
        const worker = reg.active || reg.waiting || reg.installing;
        if (!worker) return reg;
        if (worker.state === 'activated') return reg;
        return new Promise((resolve) => {
          worker.addEventListener('statechange', () => {
            if (worker.state === 'activated') resolve(reg);
          });
        });
      })
      .catch(err => { console.warn('p2p: sw register failed, falling back to blob mode', err); return null; });
    return swPromise;
  }

  // Prefer status/stage elements inside the main panel — keeps action buttons on
  // a library-item detail page from accidentally writing into the aside-right.
  function defaultStatus() {
    return document.querySelector('#panel [data-p2p-status]')
        || document.querySelector('[data-p2p-status]');
  }
  function defaultStage() {
    return document.querySelector('#panel [data-p2p-stage]')
        || document.querySelector('[data-p2p-stage]');
  }
  function setStatus(t) {
    const el = activeStatusEl || defaultStatus();
    if (el) el.textContent = t;
  }

  function fmtBytes(n) {
    if (!n || n < 0) return '0 B';
    const u = ['B','KB','MB','GB','TB'];
    let i = 0;
    while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
    return n.toFixed(n < 10 ? 1 : 0) + ' ' + u[i];
  }

  function watchTorrent(t) {
    if (statusTimer) clearInterval(statusTimer);
    statusTimer = setInterval(() => {
      const pct = (t.progress * 100).toFixed(0);
      setStatus('peers ' + t.numPeers + ' · ' + pct + '% · ↓ ' + fmtBytes(t.downloadSpeed) + '/s');
    }, 800);
    t.on('done', () => setStatus('done · ' + t.numPeers + ' peers · ' + fmtBytes(t.length)));
    t.on('error', (err) => setStatus('error: ' + (err && err.message ? err.message : err)));
    t.on('warning', () => {});
  }

  async function ensureClient() {
    await loadWebTorrent();
    if (!client) client = new window.WebTorrent();
    if (!serverStarted) {
      const reg = await registerSW();
      if (reg && typeof client.createServer === 'function') {
        try {
          client.createServer({ controller: reg });
          serverStarted = true;
        } catch (e) {
          console.warn('p2p: createServer failed, falling back to blob mode', e);
        }
      }
    }
    return client;
  }

  function clearEl(el) {
    if (!el) return;
    while (el.firstChild) el.removeChild(el.firstChild);
    el.classList.remove('vid-frame-empty');
  }

  function pickFile(t) {
    if (!t.files || !t.files.length) return null;
    return t.files.slice().sort((a, b) => b.length - a.length)[0];
  }

  function buildRenderEl(kind, src, fileName) {
    let el;
    if (kind === 'video') {
      el = document.createElement('video');
      el.controls = true; el.autoplay = true;
      el.className = 'vid-frame';
      el.style.objectFit = 'contain';
      el.style.background = '#000';
    } else if (kind === 'audio') {
      el = document.createElement('audio');
      el.controls = true; el.autoplay = true;
      el.style.width = '100%';
    } else if (kind === 'image') {
      el = document.createElement('img');
      el.alt = fileName;
      el.style.maxWidth = '100%';
      el.style.height = 'auto';
    } else if (kind === 'pdf') {
      el = document.createElement('iframe');
      el.style.width = '100%';
      el.style.minHeight = '70vh';
      el.style.border = '0';
    } else {
      el = document.createElement('a');
      el.download = fileName;
      el.textContent = '⤓ ' + fileName;
      el.className = 'usgc-badge';
    }
    el.src = src;
    return el;
  }

  async function fileBlobURL(file) {
    if (typeof file.blob === 'function') {
      const b = await file.blob();
      return URL.createObjectURL(b);
    }
    if (typeof file.getBlobURL === 'function') {
      return new Promise((resolve, reject) => {
        file.getBlobURL((err, url) => err ? reject(err) : resolve(url));
      });
    }
    throw new Error('no blob API on file');
  }

  /**
   * Start a magnet torrent and render the largest file into `stage`.
   * Uses file.streamURL when the service worker is active (progressive
   * playback) and falls back to a Blob URL otherwise.
   */
  async function open(magnet, kind, stage, statusEl) {
    activeStatusEl = statusEl || null;
    setStatus('connecting…');
    const target = stage || defaultStage();
    try {
      const c = await ensureClient();
      if (torrent) { try { torrent.destroy(); } catch (_) {} torrent = null; }
      clearEl(target);
      c.add(magnet, async (t) => {
        torrent = t;
        watchTorrent(t);
        const file = pickFile(t);
        if (!file) { setStatus('no files in torrent'); return; }
        if (serverStarted && typeof file.streamURL === 'string') {
          if (target) target.appendChild(buildRenderEl(kind, file.streamURL, file.name));
          return;
        }
        // Fallback: no SW available — wait for the full file, then blob URL.
        try {
          const url = await fileBlobURL(file);
          if (!target) return;
          target.appendChild(buildRenderEl(kind, url, file.name));
        } catch (e) {
          setStatus('render: ' + (e.message || e));
        }
      });
    } catch (e) {
      setStatus('error: ' + (e.message || e));
    }
  }

  async function download(magnet, statusEl) {
    activeStatusEl = statusEl || null;
    setStatus('connecting…');
    try {
      const c = await ensureClient();
      if (torrent) { try { torrent.destroy(); } catch (_) {} torrent = null; }
      c.add(magnet, async (t) => {
        torrent = t;
        watchTorrent(t);
        const file = pickFile(t);
        if (!file) { setStatus('no files in torrent'); return; }
        try {
          const url = await fileBlobURL(file);
          const a = document.createElement('a');
          a.href = url;
          a.download = file.name;
          document.body.appendChild(a);
          a.click();
          a.remove();
          setStatus('saved ' + file.name);
        } catch (e) {
          setStatus('blob: ' + (e.message || e));
        }
      });
    } catch (e) {
      setStatus('error: ' + (e.message || e));
    }
  }

  function copy(magnet, statusEl) {
    activeStatusEl = statusEl || null;
    if (!navigator.clipboard) { setStatus('clipboard unavailable'); return; }
    navigator.clipboard.writeText(magnet).then(
      () => setStatus('copied'),
      () => setStatus('copy failed')
    );
  }

  function teardown() {
    if (statusTimer) { clearInterval(statusTimer); statusTimer = null; }
    if (torrent) { try { torrent.destroy(); } catch (_) {} torrent = null; }
    if (client) { try { client.destroy(); } catch (_) {} client = null; }
    activeStatusEl = null;
  }

  function init() {
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-p2p-action]');
      if (!btn) return;
      e.preventDefault();
      const a = btn.dataset.p2pAction;
      const m = btn.dataset.magnet;
      const k = btn.dataset.kind || 'other';
      if (!m) return;
      if (a === 'copy') copy(m);
      else if (a === 'download') download(m);
      else if (a === 'open') open(m, k);
    });
  }

  window.cisrP2P = { teardown, init, open, download, copy };
  init();
})();
