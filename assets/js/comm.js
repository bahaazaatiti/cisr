(() => {
  if (window.siteComm) return;

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  // State
  let trysteroPromise = null;
  let room = null;
  let chatSend = null;
  let localStream = null;
  let peerVideos = new Map();   // peerId → <video> in the conf grid
  const messages = [];          // ring buffer, capped 200
  let roomEnsured = false;
  let inConference = false;
  // Connecting state — true between ensureRoom() and either first-peer or a
  // generous timeout. Used to distinguish "still negotiating with trackers"
  // from "trackers are up but no one else is here right now".
  let chatConnecting = false;
  let chatConnectingTimer = null;

  function vendorUrl() {
    const s = document.querySelector('script[data-comm-vendor]');
    return (s && s.dataset.commVendor) || '/assets/js/vendor/trystero.min.js';
  }

  function loadTrystero() {
    if (window.Trystero) return Promise.resolve(window.Trystero);
    if (trysteroPromise) return trysteroPromise;
    trysteroPromise = import(vendorUrl())
      .then(mod => { window.Trystero = mod; return mod; })
      .catch(err => { trysteroPromise = null; throw err; });
    return trysteroPromise;
  }

  // ---- DOM helpers (chat) ----
  function peerCountEls() { return $$('[data-comm-peer-count]'); }
  function msgListEls()   { return $$('[data-comm-msg-list]'); }
  function inputEls()     { return $$('[data-comm-msg-input]'); }
  function gridEls()      { return $$('[data-comm-grid]'); }
  function precallEls()   { return $$('[data-comm-precall]'); }
  function callEls()      { return $$('[data-comm-call]'); }
  function gumErrEls()    { return $$('[data-comm-gum-err]'); }

  // Security choke point: every string that flows into insertAdjacentHTML
  // (peer ids, timestamps, received chat text, system notices) MUST pass
  // through this. Adding new chat-line shapes? Escape first, concatenate
  // second — no exceptions.
  function escHtml(s) {
    return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  }
  function shortId(id) { return String(id).slice(0, 6); }
  function fmtTime(ts) {
    const d = new Date(ts);
    return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
  }
  function updatePeerCount() {
    const n = room ? Object.keys(room.getPeers ? room.getPeers() : {}).length : 0;
    peerCountEls().forEach(el => {
      if (chatConnecting && n === 0) {
        el.textContent = el.dataset.connecting || 'connecting…';
        return;
      }
      const fmt = el.dataset.fmt || '{n} peers';
      el.textContent = fmt.replace('{n}', n);
    });
  }
  function appendMsg({ peerId, text, ts, self }) {
    messages.push({ peerId, text, ts, self });
    // Ring-buffer cap: 200 lines ≈ 30-100 KB depending on text length. In-RAM
    // only — never persisted. Refresh wipes the history; that is intentional.
    if (messages.length > 200) messages.shift();
    const html = '<li><span class="comm-peer">' + escHtml(self ? 'you' : shortId(peerId)) + ' · ' + escHtml(fmtTime(ts)) + '</span> ' + escHtml(text) + '</li>';
    msgListEls().forEach(ol => {
      ol.insertAdjacentHTML('beforeend', html);
      ol.scrollTop = ol.scrollHeight;
    });
  }
  // Local-only system line — never broadcast over Trystero, never enters the
  // 200-cap messages buffer (so it can't be evicted), no peer label or time.
  function appendSystemMsg(text) {
    if (!text) return;
    const html = '<li class="comm-system">' + escHtml(text) + '</li>';
    msgListEls().forEach(ol => {
      ol.insertAdjacentHTML('beforeend', html);
      ol.scrollTop = ol.scrollHeight;
    });
  }
  function showGumError(text) {
    gumErrEls().forEach(el => { el.textContent = text || ''; });
  }

  // ---- Room lifecycle ----
  async function ensureRoom() {
    if (roomEnsured) return room;
    roomEnsured = true;
    try {
      const trystero = await loadTrystero();
      // Single global channel: appId and room are hardcoded so every fork
      // joins the same swarm by default. Neither is a secret — DHT
      // discoverable. Forks that want a private lobby fork these constants.
      room = trystero.joinRoom({ appId: 'cisr' }, 'lobby');
      // Trystero v0.25 returns an object with .send / .onMessage (older
      // versions returned a [send, onMsg] tuple — don't assume).
      const action = room.makeAction('chat');
      chatSend = action.send;
      action.onMessage = (text, info) => {
        const peerId = info && info.peerId ? info.peerId : info;
        appendMsg({ peerId, text: String(text), ts: Date.now(), self: false });
      };
      room.onPeerJoin = (peerId) => {
        chatConnecting = false;
        updatePeerCount();
        if (window.siteToast) {
          const tmpl = msgListEls()[0]?.dataset.chatPeerJoined || 'peer joined: {id}';
          window.siteToast(tmpl.replace('{id}', shortId(peerId)), 'info');
        }
      };
      room.onPeerLeave = (id) => { removePeerVideo(id); updatePeerCount(); };
      room.onPeerStream = (stream, peerId) => attachPeerVideo(peerId, stream);
      // Initial connecting state — flips to "0 peers" once trackers have had a
      // chance to report back. 6 s is enough for the WSS handshake + announce
      // round-trip on most networks without making the user wait forever.
      chatConnecting = true;
      if (chatConnectingTimer) clearTimeout(chatConnectingTimer);
      chatConnectingTimer = setTimeout(() => {
        chatConnecting = false; chatConnectingTimer = null; updatePeerCount();
      }, 6000);
      updatePeerCount();
      // First-line privacy notice (read from the aside-comm template's
      // data-chat-privacy attribute so the wording follows the page language).
      const notice = msgListEls()[0]?.dataset.chatPrivacy;
      if (notice) appendSystemMsg(notice);
    } catch (err) {
      console.warn('comm: room init failed', err);
      roomEnsured = false;
    }
    return room;
  }

  // ---- Chat actions ----
  async function sendChat(text) {
    const t = String(text || '').trim();
    if (!t) return;
    if (!chatSend) await ensureRoom();
    if (!chatSend) return;
    chatSend(t);
    appendMsg({ peerId: 'self', text: t, ts: Date.now(), self: true });
    // Trystero broadcasts without buffering — a message sent into an empty
    // lobby is lost. Surface a local-only warning so the user knows.
    const peers = room ? Object.keys(room.getPeers ? room.getPeers() : {}).length : 0;
    if (peers === 0) {
      appendSystemMsg(msgListEls()[0]?.dataset.chatNoPeers || 'no peers connected — message not delivered.');
    }
  }

  // ---- Conference actions ----
  function attachPeerVideo(peerId, stream) {
    let el = peerVideos.get(peerId);
    if (!el) {
      el = document.createElement('video');
      el.autoplay = true; el.playsInline = true;
      el.dataset.peer = peerId;
      peerVideos.set(peerId, el);
      gridEls().forEach(g => g.appendChild(el.cloneNode()));
      // Track the actually-mounted node in the first grid as the canonical one
      const live = gridEls()[0]?.querySelector(`video[data-peer="${peerId}"]`);
      if (live) { peerVideos.set(peerId, live); el = live; }
    }
    el.srcObject = stream;
  }
  function removePeerVideo(peerId) {
    gridEls().forEach(g => {
      const v = g.querySelector(`video[data-peer="${peerId}"]`);
      if (v) v.remove();
    });
    peerVideos.delete(peerId);
  }
  function showLocalVideo(stream) {
    gridEls().forEach(g => {
      let v = g.querySelector('video[data-peer="self"]');
      if (!v) {
        v = document.createElement('video');
        v.autoplay = true; v.playsInline = true; v.muted = true;
        v.dataset.peer = 'self';
        g.appendChild(v);
      }
      v.srcObject = stream;
    });
  }
  function clearConfGrid() {
    gridEls().forEach(g => { while (g.firstChild) g.removeChild(g.firstChild); });
    peerVideos.clear();
  }
  function flipConfView(joined) {
    precallEls().forEach(el => { el.hidden = joined; });
    callEls().forEach(el => { el.hidden = !joined; });
  }

  async function joinConference() {
    if (inConference) return;
    showGumError('');
    // Try audio + video first; on failure (no camera, camera in use by another
    // app, etc.) retry with audio only so users without a webcam can still
    // talk and see other peers' video.
    try {
      localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
    } catch (avErr) {
      try {
        localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
      } catch (audioErr) {
        const fallback = gumErrEls()[0]?.dataset.fallback || 'Camera/microphone access denied.';
        showGumError(fallback + (audioErr?.message ? ' — ' + audioErr.message : ''));
        return;
      }
    }
    await ensureRoom();
    if (!room) {
      localStream.getTracks().forEach(t => t.stop());
      localStream = null;
      return;
    }
    inConference = true;
    flipConfView(true);
    showLocalVideo(localStream);
    try { room.addStream(localStream); } catch (_) {}
  }

  function leaveConference() {
    if (!inConference) return;
    try { if (room && localStream) room.removeStream(localStream); } catch (_) {}
    if (localStream) { localStream.getTracks().forEach(t => t.stop()); localStream = null; }
    clearConfGrid();
    flipConfView(false);
    inConference = false;
  }

  function toggleTrack(kind) {
    if (!localStream) return null;
    const tracks = localStream.getTracks().filter(t => t.kind === kind);
    if (!tracks.length) return null;
    const next = !tracks[0].enabled;
    tracks.forEach(t => { t.enabled = next; });
    return next;
  }

  function teardown() {
    try { leaveConference(); } catch (_) {}
    try { if (room) room.leave(); } catch (_) {}
    room = null; chatSend = null;
    roomEnsured = false;
    messages.length = 0;
    msgListEls().forEach(ol => { ol.innerHTML = ''; });
  }

  // ---- Wiring ----
  function init() {
    // Lazy-ensure room the first time the user opens the COMM drawer.
    document.addEventListener('click', (e) => {
      const tog = e.target.closest('[data-drawer-toggle="comm"]');
      if (tog) { ensureRoom(); return; }

      if (e.target.closest('[data-comm-send]')) {
        e.preventDefault();
        const inp = inputEls()[0];
        if (!inp) return;
        sendChat(inp.value);
        inp.value = '';
        return;
      }
      if (e.target.closest('[data-comm-join-conf]')) { e.preventDefault(); joinConference(); return; }
      if (e.target.closest('[data-comm-leave-conf]')) { e.preventDefault(); leaveConference(); return; }
      if (e.target.closest('[data-comm-mute-mic]')) {
        e.preventDefault();
        const on = toggleTrack('audio');
        if (on != null) e.target.closest('[data-comm-mute-mic]').classList.toggle('active', on);
        return;
      }
      if (e.target.closest('[data-comm-mute-cam]')) {
        e.preventDefault();
        const on = toggleTrack('video');
        if (on != null) e.target.closest('[data-comm-mute-cam]').classList.toggle('active', on);
        return;
      }
    });

    // Enter (without Shift) in composer sends.
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter' || e.shiftKey) return;
      const inp = e.target.closest('[data-comm-msg-input]');
      if (!inp) return;
      e.preventDefault();
      sendChat(inp.value);
      inp.value = '';
    });

    // Make sure rooms are torn down on full page unload.
    addEventListener('beforeunload', teardown);
  }

  window.siteComm = { init, teardown, ensureRoom, sendChat, joinConference, leaveConference };
  init();
})();
