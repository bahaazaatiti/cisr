/* Live news crawl — browser side.
 *
 * Hand-written "breaking" lines arrive server-rendered into .tk-track (instant,
 * and they survive a dead network). This module fetches the latest posts at
 * view time and rebuilds the marquee with native + fetched lines interleaved.
 *
 * Strictly browser-only — no backend, no API keys. The editor pastes a raw
 * t.me / x.com link; we read it cross-origin through a CORS proxy (Telegram
 * from its public web preview, X from a nitter mirror's RSS). Proxies and
 * nitter instances are tried in order, so one dead host doesn't blank a lane;
 * if every host fails the lane just goes dark. X rides public nitter, so it is
 * best-effort by nature.
 *
 * Rendered once in the persistent shell, so it runs once per full load and
 * rides across SPA navigations untouched. */
(function () {
  'use strict';
  if (window.siteTicker) return;

  var ticker = document.querySelector('.ticker[data-ticker]');
  if (!ticker) return;
  var track = ticker.querySelector('.tk-track');
  var cfgEl = ticker.querySelector('.tk-config');

  var cfg = {};
  try { cfg = JSON.parse(cfgEl.textContent); } catch (e) {}
  var feeds    = Array.isArray(cfg.feeds) ? cfg.feeds : [];
  var livenews = Array.isArray(cfg.livenews) ? cfg.livenews : [];
  if ((!feeds.length && !livenews.length) || !track) { window.siteTicker = { teardown: function () {} }; return; }

  // Fallback chains — first host that returns usable data wins. The live lists
  // come from cfg (curated in TRACKERS.md → config option, resolved server-side
  // in ticker.php); these literals are only the last-resort default if cfg is
  // empty, so nothing host-specific is hardcoded as the source of truth.
  var PROXIES = (cfg.proxies && cfg.proxies.length) ? cfg.proxies : ['https://api.allorigins.win/raw?url='];
  var NITTERS = (cfg.nitters && cfg.nitters.length) ? cfg.nitters : ['https://nitter.net'];
  var MAX    = cfg.max || 5;
  var CAP    = cfg.cap || 160;
  var TTL    = (cfg.ttl || 180) * 1000;
  var SVGNS  = 'http://www.w3.org/2000/svg';

  // Hand-written lines already in the DOM = the always-present base layer.
  var baseSeg = track.querySelector('.tk-seg');
  var nativeNodes = baseSeg ? Array.prototype.map.call(baseSeg.children, function (n) { return n.cloneNode(true); }) : [];
  var remoteLanes = [];   // fetched feed lanes (filled by run())
  var liveItems   = [];   // live-news lines, newest first (filled live), capped

  function cacheGet(k) {
    try { var r = JSON.parse(sessionStorage.getItem(k)); if (r && Date.now() - r.t < TTL) return r.v; } catch (e) {}
    return null;
  }
  function cacheSet(k, v) { try { sessionStorage.setItem(k, JSON.stringify({ t: Date.now(), v: v })); } catch (e) {} }
  // Shared cache wrapper for a source lane: serve a fresh cache hit, else run
  // produce(). Only NON-EMPTY results are cached (an empty/failed lane retries
  // next view rather than caching darkness for the TTL); any rejection → [].
  function cachedSource(key, produce) {
    var hit = cacheGet(key);
    if (hit) return Promise.resolve(hit);
    return produce().then(function (v) {
      if (!v || !v.length) return [];
      cacheSet(key, v);
      return v;
    }).catch(function () { return []; });
  }

  function fetchText(url, ms) {
    var ctrl = new AbortController();
    var timer = setTimeout(function () { ctrl.abort(); }, ms || 13000);
    return fetch(url, { signal: ctrl.signal, credentials: 'omit' })
      .then(function (res) { if (!res.ok) throw new Error(res.status); return res.text(); })
      .then(function (text) { clearTimeout(timer); return text; })
      .catch(function (err) { clearTimeout(timer); throw err; });
  }

  // Pull a cross-origin URL through the proxy chain — first non-empty body wins.
  function fetchVia(target, ms) {
    var i = 0;
    return (function next() {
      if (i >= PROXIES.length) return Promise.reject(new Error('no proxy'));
      return fetchText(PROXIES[i++] + encodeURIComponent(target), ms)
        .then(function (t) { if (!t || !t.trim()) throw new Error('empty'); return t; })
        .catch(next);
    })();
  }

  function clean(s) { return s.replace(/\s+/g, ' ').trim(); }
  function trunc(s) { return s.length > CAP ? s.slice(0, CAP - 1).replace(/\s+\S*$/, '') + '…' : s; }

  function parseRss(xml, kind, fallbackUrl) {
    var doc = new DOMParser().parseFromString(xml, 'text/xml');
    if (doc.querySelector('parsererror')) throw new Error('bad xml');
    var entries = Array.prototype.slice.call(doc.querySelectorAll('item, entry')).slice(0, MAX);
    var out = [];
    entries.forEach(function (e) {
      var tEl = e.querySelector('title');
      var t = tEl ? clean(tEl.textContent) : '';
      if (!t) return;
      var lEl = e.querySelector('link');
      var link = (lEl && (lEl.textContent.trim() || lEl.getAttribute('href'))) || fallbackUrl;
      out.push({ kind: kind, text: trunc(t), url: link });
    });
    return out;
  }

  // Telegram: scrape the channel's public web preview through the proxy chain.
  function getTelegramRaw(input) {
    var h = input.replace(/^@/, '').replace(/^https?:\/\/(?:t|telegram)\.me\/(?:s\/)?/i, '').replace(/[/?#].*$/, '').trim();
    if (!h) return Promise.resolve([]);
    return cachedSource('tk:tg:' + h, function () {
      return fetchVia('https://t.me/s/' + h).then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var msgs = Array.prototype.slice.call(doc.querySelectorAll('.tgme_widget_message')).slice(-MAX).reverse();
        var out = [];
        msgs.forEach(function (m) {
          var el = m.querySelector('.tgme_widget_message_text');
          var txt = el ? clean(el.textContent) : '';
          if (!txt) return;
          var date = m.querySelector('a.tgme_widget_message_date');
          out.push({ kind: 'tg', text: trunc(txt), url: (date && date.href) || ('https://t.me/' + h) });
        });
        return out;
      });
    });
  }

  // X: read a nitter mirror's RSS for the handle. Walk the instance list (each
  // through the proxy chain) until one answers with posts. Public nitter is
  // flaky, so this is best-effort and may come back empty.
  function getTwitterRaw(input) {
    var h = input.replace(/^@/, '').replace(/^https?:\/\/(?:www\.)?(?:x|twitter|nitter)\.[^/]+\//i, '').replace(/[/?#].*$/, '').trim();
    if (!h) return Promise.resolve([]);
    // Walk the nitter list (each via the proxy chain); first non-empty wins. The
    // shared wrapper handles the cache + the all-failed → [] fallback.
    return cachedSource('tk:x:' + h, function () {
      var i = 0;
      return (function next() {
        if (i >= NITTERS.length) return [];
        var base = NITTERS[i++].replace(/\/+$/, '');
        return fetchVia(base + '/' + h + '/rss').then(function (xml) {
          var v = parseRss(xml, 'x', 'https://x.com/' + h);
          if (!v.length) throw new Error('empty');
          return v;
        }).catch(next);
      })();
    });
  }

  // The field decides the source kind, so routing is just kind → fetcher.
  function getSource(f) {
    return f.kind === 'tg' ? getTelegramRaw(f.url) : getTwitterRaw(f.url);
  }

  function logo(kind) {
    var svg = document.createElementNS(SVGNS, 'svg');
    svg.setAttribute('class', 'tk-logo');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    var use = document.createElementNS(SVGNS, 'use');
    use.setAttribute('href', kind === 'x' ? '#tk-x' : '#tk-tg');
    svg.appendChild(use);
    return svg;
  }

  function itemNode(it) {
    var href = /^https?:\/\//i.test(it.url) ? it.url : null;
    var el = document.createElement(href ? 'a' : 'span');
    el.className = 'tk-item';
    if (href) { el.href = href; el.target = '_blank'; el.rel = 'noopener noreferrer'; }
    el.appendChild(logo(it.kind));
    var span = document.createElement('span');
    span.className = 'tk-text';
    span.textContent = it.text;            // untrusted content → textContent, never innerHTML
    el.appendChild(span);
    return el;
  }

  // Round-robin merge so platforms alternate (native, tg, x, native, tg, …).
  function interleave(lanes) {
    lanes = lanes.filter(function (l) { return l.length; });
    var out = [], i = 0, more = true;
    while (more) {
      more = false;
      lanes.forEach(function (lane) { if (i < lane.length) { out.push(lane[i]); more = true; } });
      i++;
    }
    return out;
  }

  function paint(lanes) {
    var merged = interleave(lanes);
    if (!merged.length) {
      // Nothing to show. If live-news rooms are configured, keep the band
      // reserved (lines may still arrive) instead of hiding it — hiding drops
      // has-ticker and shifts the layout. Otherwise fall back to the legacy hide.
      if (!livenews.length) { ticker.hidden = true; document.body.classList.remove('has-ticker'); }
      return;
    }
    ticker.hidden = false;

    var chars = 0;
    merged.forEach(function (n) { chars += (n.textContent || '').length + 4; });
    track.style.setProperty('--ticker-dur', Math.max(20, Math.round(chars * 0.11)) + 's');

    function makeSeg(hidden) {
      var seg = document.createElement('div');
      seg.className = 'tk-seg';
      // The duplicate is decoration for the seamless loop: aria-hidden hides it
      // from AT, inert keeps its links out of the tab order (focusable copies
      // under aria-hidden fail axe's aria-hidden-focus rule).
      if (hidden) { seg.setAttribute('aria-hidden', 'true'); seg.inert = true; }
      merged.forEach(function (n) { seg.appendChild(n.cloneNode(true)); });
      return seg;
    }
    track.replaceChildren(makeSeg(false), makeSeg(true));
  }

  // Rebuild the crawl from every current lane: server-rendered native lines,
  // fetched feed lanes, then the live-news lane.
  function repaint() {
    var lanes = [nativeNodes].concat(remoteLanes);
    if (liveItems.length) lanes.push(liveItems);
    paint(lanes);
  }

  function run() {
    Promise.allSettled(feeds.map(getSource)).then(function (results) {
      results.forEach(function (r) {
        if (r.status === 'fulfilled' && r.value && r.value.length) remoteLanes.push(r.value.map(itemNode));
      });
      if (remoteLanes.length) repaint();   // merge fetched lanes; else keep native
    });
  }

  // ---- Live news: a signed editor's chat, straight into the crawl ----
  // Each row is an independent receiver — comm.js owns the room join + per-message
  // signature verification (siteComm.signedReceiver); we only render the verified
  // lines, marked with a flickering dot. CAP at 1 room for now (receivers are
  // independent, so lifting the cap is a one-line change).
  var LIVE_CAP = 8;                       // most live lines kept in the crawl
  var rooms = livenews.slice(0, 1);       // cap at 1 room for now
  var liveHandles = [];

  function liveItemNode(label, text) {
    var el = document.createElement('span');
    el.className = 'tk-item';
    var dot = document.createElement('span');
    dot.className = 'tk-dot tk-dot-live';   // flickering = live
    dot.setAttribute('aria-hidden', 'true');
    el.appendChild(dot);
    var span = document.createElement('span');
    span.className = 'tk-text';
    span.textContent = (label ? label + ' · ' : '') + text;   // untrusted → textContent
    el.appendChild(span);
    return el;
  }

  function startLiveNews() {
    if (!window.siteComm || !window.siteComm.signedReceiver) return false;
    rooms.forEach(function (r) {
      if (!r || !r.room || !r.pubkey) return;
      window.siteComm.signedReceiver(r.room, r.pubkey, {
        onLine: function (text) {
          var t = trunc(clean(String(text)));
          if (!t) return;
          liveItems.unshift(liveItemNode(r.label || '', t));
          if (liveItems.length > LIVE_CAP) liveItems.length = LIVE_CAP;
          repaint();
        }
      }).then(function (h) { if (h) liveHandles.push(h); });
    });
    return true;
  }

  window.siteTicker = { teardown: function () {
    liveHandles.forEach(function (h) { try { h.teardown(); } catch (e) {} });
  } };

  if (feeds.length) run();
  if (rooms.length && !startLiveNews()) {
    // comm.js loads deferred too; poll briefly for its API like broadcast.js.
    var tries = 0;
    var lt = setInterval(function () { if (startLiveNews() || ++tries > 50) clearInterval(lt); }, 100);
  }
})();
