(() => {
  if (window.siteP2P) return;

  let client = null;
  let viewerTorrent = null;
  let viewerStatusTimer = null;
  let activeStatusEl = null;
  let wtPromise = null;
  let swPromise = null;
  let serverStarted = false;

  // Mirror is a separate pool — opt-in, persists across SPA nav, runs alongside
  // any viewer torrent without disturbing it. Tracks status sinks per active
  // section (library / videos) so the aggregate line appears in each.
  const mirrorTorrents = new Map();   // magnet → torrent (includes both downloading + seeding)
  const mirrorInFlight = new Set();   // subset still downloading; gates concurrency cap
  const mirrorSections = new Map();   // magnet → section label ('library' | 'videos')
  const mirrorStatusEls = new Map();  // section → status element
  let mirrorStatusTimer = null;

  function vendorUrl() {
    const s = document.querySelector('script[data-vendor]');
    if (s && s.dataset.vendor) return s.dataset.vendor;
    return '/assets/js/vendor/webtorrent.min.js';
  }

  function swUrl() {
    // data-sw is rendered with Kirby's url() so it respects the deploy base.
    const s = document.querySelector('script[data-sw]');
    if (s && s.dataset.sw) return s.dataset.sw;
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

  // SW unlocks file.streamURL for progressive playback (vs blob fallback).
  function registerSW() {
    if (swPromise) return swPromise;
    if (!('serviceWorker' in navigator)) {
      swPromise = Promise.resolve(null);
      return swPromise;
    }
    swPromise = navigator.serviceWorker.register(swUrl())
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

  // Prefer #panel-local status/stage so detail-page actions don't write into the aside.
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

  // Viewer-only status painter — independent of the mirror's painter so the
  // two don't fight when a viewer download runs while a mirror is active.
  function watchViewer(t) {
    if (viewerStatusTimer) clearInterval(viewerStatusTimer);
    viewerStatusTimer = setInterval(() => {
      if (t.done) {
        setStatus('peers ' + t.numPeers + ' · ↑ ' + fmtBytes(t.uploadSpeed) + '/s');
      } else {
        const pct = (t.progress * 100).toFixed(0);
        setStatus('peers ' + t.numPeers + ' · ' + pct + '% · ↓ ' + fmtBytes(t.downloadSpeed) + '/s');
      }
    }, 800);
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
      el.className = 'ui-badge';
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
   * Start a magnet and render its largest file into `stage`. SW path uses
   * streamURL for progressive playback; blob fallback waits for full download.
   */
  async function open(magnet, kind, stage, statusEl) {
    activeStatusEl = statusEl || null;
    setStatus('connecting…');
    const target = stage || defaultStage();
    try {
      const c = await ensureClient();
      if (viewerTorrent) { try { viewerTorrent.destroy(); } catch (_) {} viewerTorrent = null; }
      clearEl(target);
      c.add(magnet, async (t) => {
        viewerTorrent = t;
        watchViewer(t);
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
      if (viewerTorrent) { try { viewerTorrent.destroy(); } catch (_) {} viewerTorrent = null; }
      c.add(magnet, async (t) => {
        viewerTorrent = t;
        watchViewer(t);
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

  // ---- Mirror ----

  function paintMirrorStatus() {
    if (!mirrorStatusEls.size) return;
    mirrorStatusEls.forEach((el, section) => {
      const tors = sectionTorrents(section);
      if (!tors.length) { el.textContent = ''; return; }
      let done = 0, upSpeed = 0, dl = 0, len = 0;
      tors.forEach(t => {
        if (t.done) done++;
        upSpeed += t.uploadSpeed || 0;
        dl += t.downloaded || 0;
        len += t.length || 0;
      });
      if (done === tors.length) {
        el.textContent = 'mirroring ' + tors.length + ' · ↑ ' + fmtBytes(upSpeed) + '/s';
      } else {
        const pct = len ? Math.floor((dl / len) * 100) : 0;
        el.textContent = 'mirroring ' + done + '/' + tors.length + ' · ' + pct + '% · ↑ ' + fmtBytes(upSpeed) + '/s';
      }
    });
  }
  function sectionTorrents(section) {
    const out = [];
    mirrorSections.forEach((sec, magnet) => {
      if (sec === section) {
        const t = mirrorTorrents.get(magnet);
        if (t) out.push(t);
      }
    });
    return out;
  }

  // Scan a list of magnets to see which actually have peers in the swarm right
  // now. Adds each torrent briefly with all pieces deselected (so we don't
  // accidentally start a download), waits for the tracker to report peers,
  // then destroys the probe torrents. Returns survivors sorted by demand.
  async function mirrorScan(magnets, statusEl) {
    if (statusEl) statusEl.textContent = 'scanning swarm…';
    try {
      await ensureClient();
    } catch (e) {
      if (statusEl) statusEl.textContent = 'scan failed: ' + (e.message || e);
      return [];
    }
    const probes = magnets.map(magnet => new Promise(resolve => {
      let t;
      try {
        t = client.add(magnet);
      } catch (_) { resolve(null); return; }
      const onMeta = () => {
        try { t.deselect(0, t.pieces.length - 1, true); } catch (_) {}
      };
      t.on('metadata', onMeta);
      t.on('error', () => {});
      t.on('warning', () => {});
      setTimeout(() => {
        const result = { magnet, numPeers: t.numPeers || 0, size: t.length || 0 };
        try { t.destroy(); } catch (_) {}
        resolve(result);
      }, 2500);
    }));
    const results = (await Promise.all(probes)).filter(Boolean);
    // Return every probe; the caller decides whether to mirror only those with
    // peers waiting, or fall back to mirroring everything when no one's around.
    // Sort by numPeers desc so the most-demanded items naturally come first.
    return results.sort((a, b) => b.numPeers - a.numPeers);
  }

  async function mirrorStart(targets, statusEl, section) {
    if (!targets || !targets.length) return;
    const sec = section || 'default';
    try {
      await ensureClient();
    } catch (e) {
      if (statusEl) statusEl.textContent = 'mirror failed: ' + (e.message || e);
      return;
    }
    if (statusEl) mirrorStatusEls.set(sec, statusEl);
    const queue = targets.slice();
    // Read cap from <html data-mirror-cap> so forks can tune without editing
    // JS. Fallback to 3 if attribute missing or non-numeric.
    const cap = parseInt(document.documentElement.dataset.mirrorCap, 10) || 3;
    function spawn() {
      // Cap counts ACTIVELY downloading torrents (mirrorInFlight), not the
      // total mirror pool — completed ones stay seeding but no longer occupy
      // a download slot. Global cap across all sections.
      while (mirrorInFlight.size < cap && queue.length) {
        const item = queue.shift();
        const magnet = item.magnet || item;
        if (mirrorTorrents.has(magnet)) continue;
        let t;
        try { t = client.add(magnet); } catch (_) { continue; }
        mirrorTorrents.set(magnet, t);
        mirrorSections.set(magnet, sec);
        if (!t.done) mirrorInFlight.add(t);
        t.on('error', () => { mirrorInFlight.delete(t); spawn(); });
        t.on('warning', () => {});
        t.on('done', () => {
          mirrorInFlight.delete(t);
          spawn();
          // Surface completion as a toast — the running status line tells you
          // how the pool is doing in aggregate, but a finished mirror is a
          // moment worth announcing once.
          if (window.siteToast) {
            const name = t.name || (t.files && t.files[0] && t.files[0].name) || 'item';
            window.siteToast('mirror complete: ' + name, 'success');
          }
        });
      }
    }
    spawn();
    if (!mirrorStatusTimer) {
      mirrorStatusTimer = setInterval(paintMirrorStatus, 800);
    }
    paintMirrorStatus();
  }

  function mirrorStop(section) {
    if (section) {
      // Destroy only this section's torrents.
      const sec = section;
      const dead = [];
      mirrorSections.forEach((s, magnet) => { if (s === sec) dead.push(magnet); });
      dead.forEach(magnet => {
        const t = mirrorTorrents.get(magnet);
        if (t) { mirrorInFlight.delete(t); try { t.destroy(); } catch (_) {} }
        mirrorTorrents.delete(magnet);
        mirrorSections.delete(magnet);
      });
      const el = mirrorStatusEls.get(sec);
      if (el) el.textContent = '';
      mirrorStatusEls.delete(sec);
      if (!mirrorTorrents.size && mirrorStatusTimer) {
        clearInterval(mirrorStatusTimer); mirrorStatusTimer = null;
      }
      return;
    }
    // Full stop — every section.
    if (mirrorStatusTimer) { clearInterval(mirrorStatusTimer); mirrorStatusTimer = null; }
    mirrorTorrents.forEach(t => { try { t.destroy(); } catch (_) {} });
    mirrorTorrents.clear();
    mirrorInFlight.clear();
    mirrorSections.clear();
    mirrorStatusEls.forEach(el => { el.textContent = ''; });
    mirrorStatusEls.clear();
  }

  function mirrorIsActive(section) {
    if (!section) return mirrorTorrents.size > 0;
    let n = 0;
    mirrorSections.forEach(s => { if (s === section) n++; });
    return n > 0;
  }

  // SPA-scope teardown: only the viewer torrent, never the client or mirror.
  // The full client + mirror are torn down by the beforeunload handler.
  function teardown() {
    if (viewerStatusTimer) { clearInterval(viewerStatusTimer); viewerStatusTimer = null; }
    if (viewerTorrent) { try { viewerTorrent.destroy(); } catch (_) {} viewerTorrent = null; }
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
    // True page exit destroys everything; SPA nav only clears the viewer.
    addEventListener('beforeunload', () => {
      try { mirrorStop(); } catch (_) {}
      try { teardown(); } catch (_) {}
      if (client) { try { client.destroy(); } catch (_) {} client = null; }
    });
  }

  window.siteP2P = { teardown, init, open, download, copy, mirrorScan, mirrorStart, mirrorStop, mirrorIsActive };
  init();
})();
