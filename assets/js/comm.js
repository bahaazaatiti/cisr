(() => {
  if (window.siteComm) return;

  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  // ---- Transport state ----
  let T = null;                 // Trystero module (lazy import)
  let selfId = '';
  let room = null;
  let act = {};                 // action senders, keyed by channel id
  let joined = false;
  let roomName = 'lobby';
  let roomPass = '';
  let relayTimer = null;
  let networkReady = false;     // we've actually reached the swarm, not just opened a socket
  let graceTimer = null;        // alone-resolution fallback armed once the relay opens
  let peerInbound = false;      // the relay forwarded a peer's offer — we're NOT alone
  let graceTries = 0;           // bounds how long we wait for that inbound peer to finish

  // ---- Domain state ----
  const peers = new Map();      // peerId → {nick,mic,cam,screen,lat,typing,stream,analyser,freq}
  const messages = [];          // {id,peerId,text,ts,self} — ring buffer, cap 200, RAM only
  const reacts = new Map();     // mid → Map(emoji → count)
  let msgSeq = 0;
  // History-sync gate: a fresh tab asks the first peer it meets for recent
  // lines exactly once. Replaces the old 6 s "connecting" guess entirely.
  let synced = false;

  let nick = '';
  try { nick = sessionStorage.getItem('comm:nick') || ''; } catch (_) {}

  // ---- Conference state ----
  let localStream = null;
  let camTrack = null;          // saved camera track, restored after screen-share
  let screenFlag = false;
  let inConference = false;
  let audioCtx = null;
  let localAnalyser = null, localFreq = null;
  let levelTimer = null, pingTimer = null;
  let typingTimer = null, myTyping = false;

  const EMOJI = ['👍','❤️','🔥','😂','🎉','✊','👀','😀'];
  const SPEAK_TH = 14;          // mean FFT magnitude above which a tile "speaks"

  // ---- Vendor load ----
  function vendorUrl() {
    const s = $('script[data-comm-vendor]');
    return (s && s.dataset.commVendor) || '/assets/js/vendor/trystero.min.js';
  }
  function loadTrystero() {
    if (T) return Promise.resolve(T);
    return import(vendorUrl()).then(m => { T = m; selfId = m.selfId || ''; return m; });
  }
  function relayOnline() {
    try {
      const s = (T && T.getRelaySockets) ? T.getRelaySockets() : {};
      return Object.values(s).some(w => w && w.readyState === 1);
    } catch (_) { return false; }
  }

  // ---- DOM getters ----
  const peerCountEls = () => $$('[data-comm-peer-count]');
  const connEls   = () => $$('[data-comm-conn]');
  const rosterEls = () => $$('[data-comm-roster]');
  const msgListEls= () => $$('[data-comm-msg-list]');
  const typingEls = () => $$('[data-comm-typing]');
  const inputEls  = () => $$('[data-comm-msg-input]');
  const gridEls   = () => $$('[data-comm-grid]');
  const precallEls= () => $$('[data-comm-precall]');
  const callEls   = () => $$('[data-comm-call]');
  const gumErrEls = () => $$('[data-comm-gum-err]');
  function tmpl(key, fallback) {
    const el = msgListEls()[0] || $('[data-comm-msg-list]');
    return (el && el.dataset[key]) || fallback;
  }

  // Security choke point: everything flowing into insertAdjacentHTML/innerHTML
  // (peer ids, nicks, chat text, times) MUST pass through escHtml first.
  function escHtml(s) {
    return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  }
  function shortId(id) { return String(id || '').slice(0, 6); }
  function fmtTime(ts) {
    const d = new Date(ts);
    return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
  }
  // Self-asserted nick is spoofable, so it's always shown *with* the real
  // short id — the id is the cryptographic identity, the nick is a label.
  function label(id) {
    if (id === 'self' || id === selfId) {
      const sid = selfId ? shortId(selfId) : 'you';
      return nick ? nick + ' · ' + sid : 'you';
    }
    const p = peers.get(id);
    const sid = shortId(id);
    return (p && p.nick) ? p.nick + ' · ' + sid : sid;
  }
  function initial(id) {
    const p = id === 'self' ? { nick } : peers.get(id);
    const n = (p && p.nick) || '';
    return (n ? n[0] : (id === 'self' ? (selfId[0] || '?') : id[0]) || '?').toUpperCase();
  }

  // ---- Connection + roster ----
  // "connected" must mean we've reached the swarm — NOT just that a relay socket
  // opened (that happens ~0.5s in, well before any peer, so joiners would see
  // "connected · 0 peers" while peers are still handshaking). Trystero exposes no
  // announce-ack and getPeers() only populates at onPeerJoin, so we hold
  // "connecting" until the first peer joins OR a short grace proves we're alone
  // (first in the room). networkReady is sticky across room hops — hopping stays
  // instant — while a relay drop still falls back to "connecting" via relayOnline.
  function connectGraceMs() {
    return parseInt(document.documentElement.dataset.commGrace, 10) || 4000;
  }
  function markReady() {
    if (graceTimer) { clearTimeout(graceTimer); graceTimer = null; }
    if (networkReady) return;
    networkReady = true;
    updateStatus();
  }
  // The relay forwards a peer's offer the instant someone is in the room — the
  // earliest "you're not alone" sign, and it lands well before the (slow, cold)
  // first WebRTC handshake. Sniff it passively (Trystero keeps its own onmessage)
  // so we don't prematurely flip to "0 peers · alone" while a peer is connecting.
  function onRelayMessage(ev) {
    if (networkReady || peerInbound) return;
    const d = ev && ev.data;
    if (typeof d !== 'string' || (d.indexOf('"offer"') < 0 && d.indexOf('"answer"') < 0)) return;
    try { const m = JSON.parse(d); if (m && (m.offer || m.answer)) peerInbound = true; } catch (_) {}
  }
  function watchRelayOffers() {
    try {
      const s = (T && T.getRelaySockets) ? T.getRelaySockets() : {};
      Object.values(s).forEach(ws => { if (ws && !ws._commSniff) { ws._commSniff = true; ws.addEventListener('message', onRelayMessage); } });
    } catch (_) {}
  }
  // Resolve "are we alone?" once the relay is up. If a peer's offer has shown up
  // we're NOT alone — stay "connecting" and re-check, bounded so a stale/foreign
  // offer can't hang us. onPeerJoin short-circuits the whole thing.
  function onGrace() {
    graceTimer = null;
    if (!networkReady && peerInbound && graceTries < 5) {
      graceTries++;
      graceTimer = setTimeout(onGrace, connectGraceMs());
      return;
    }
    markReady();
  }
  function armConnectGrace() {
    if (networkReady || graceTimer) return;
    graceTimer = setTimeout(onGrace, connectGraceMs());
  }
  function pollRelays() {
    let tries = 0;
    clearInterval(relayTimer);
    relayTimer = setInterval(() => {
      watchRelayOffers();
      updateStatus();
      if (relayOnline()) {
        armConnectGrace();   // socket up — start resolving alone-vs-peers
        clearInterval(relayTimer); relayTimer = null;
      } else if (++tries > 12) { clearInterval(relayTimer); relayTimer = null; }
    }, 1000);
  }
  function updateStatus() {
    const n = peers.size;
    const on = relayOnline() && networkReady;
    connEls().forEach(el => {
      el.dataset.state = on ? 'on' : 'off';
      el.title = on ? (el.dataset.online || 'connected') : (el.dataset.offline || 'connecting…');
    });
    // Mirror connection onto the always-visible COMMS toggle — a slow, forever
    // flicker that says "you're in the swarm" even with the drawer closed.
    $$('[data-drawer-toggle="comm"]').forEach(b => b.classList.toggle('comm-live', on));
    peerCountEls().forEach(el => {
      if (!on) { el.textContent = el.dataset.connecting || 'connecting…'; return; }
      const fmt = el.dataset.fmt || '{n} peers';
      el.textContent = fmt.replace('{n}', n);
    });
    // Block composing until we're actually on the network, not just socket-open.
    inputEls().forEach(i => { i.disabled = !on; });
    $$('[data-comm-send]').forEach(b => { b.disabled = !on; });
  }
  function rosterRow(id) {
    const p = id === 'self' ? null : peers.get(id);
    const me = id === 'self';
    // Media badges only make sense for people actually in the conference;
    // a chat-only peer has no mic to be "muted".
    const conf = me ? inConference : !!(p && p.conf);
    const mic = me ? micOn() : (p && p.mic);
    const cam = me ? camOn() : (p && p.cam);
    const scr = me ? screenFlag : (p && p.screen);
    const lat = !me && p && p.lat != null ? '<span class="comm-lat">' + escHtml(p.lat + 'ms') + '</span>' : '';
    let badges = '';
    if (conf) {
      if (mic === false) badges += '<span class="comm-b" title="muted">🔇</span>';
      if (scr) badges += '<span class="comm-b" title="sharing screen">🖥</span>';
      else if (cam) badges += '<span class="comm-b" title="camera on">🎥</span>';
      else badges += '<span class="comm-b" title="in conference">🎧</span>';
    }
    return '<li' + (me ? ' class="comm-self"' : '') + '><span class="comm-dot"></span>'
         + '<span class="comm-rn">' + escHtml(label(id)) + '</span>'
         + badges + lat + '</li>';
  }
  function renderRoster() {
    const rows = [rosterRow('self')];
    peers.forEach((_, id) => rows.push(rosterRow(id)));
    rosterEls().forEach(ul => ul.innerHTML = rows.join(''));
  }
  function renderTyping() {
    const names = [];
    peers.forEach((p, id) => { if (p && p.typing) names.push(label(id)); });
    const txt = !names.length ? ''
      : names.length === 1 ? (tmpl('typingOne', '{name} is typing…').replace('{name}', names[0]))
      : (tmpl('typingMany', '{n} people are typing…').replace('{n}', names.length));
    typingEls().forEach(el => el.textContent = txt);
  }

  // ---- Messages + reactions ----
  function msgHtml(m) {
    if (m.sys) return '<li class="comm-system">' + escHtml(m.text) + '</li>';
    const who = escHtml(label(m.self ? 'self' : m.peerId));
    return '<li data-mid="' + escHtml(m.id) + '">'
      + '<span class="comm-peer">' + who + ' · ' + escHtml(fmtTime(m.ts)) + '</span> '
      + '<span class="comm-text">' + escHtml(m.text) + '</span>'
      + '<button class="comm-react-add" type="button" data-react-add="' + escHtml(m.id) + '" aria-label="react">+</button>'
      + '<span class="comm-reacts" data-reacts="' + escHtml(m.id) + '"></span></li>';
  }
  function appendMsgLi(m) {
    const html = msgHtml(m);
    msgListEls().forEach(ol => {
      ol.insertAdjacentHTML('beforeend', html);
      ol.scrollTop = ol.scrollHeight;
    });
    renderReacts(m.id);
  }
  function renderAllMsgs() {
    const notice = tmpl('chatPrivacy', '');
    const head = notice ? '<li class="comm-system">' + escHtml(notice) + '</li>' : '';
    const html = head + messages.map(msgHtml).join('');
    msgListEls().forEach(ol => { ol.innerHTML = html; ol.scrollTop = ol.scrollHeight; });
    messages.forEach(m => renderReacts(m.id));
  }
  function addMessage(m) {
    if (messages.some(x => x.id === m.id)) return;   // dedupe (history merge + live)
    messages.push(m);
    if (messages.length > 200) { const d = messages.shift(); reacts.delete(d.id); }
    appendMsgLi(m);
  }
  // Local-only timeline line (joins, leaves, nick changes). Tagged sys:true so
  // it renders as a notice, carries no peer label/react UI, and is filtered out
  // of history sync (serialMsg) — it never leaves this tab.
  function addSystemLine(text, alert = true) {
    if (!text) return;
    addMessage({ id: 'sys-' + (msgSeq++), sys: true, text: String(text), ts: Date.now() });
    if (alert) bumpChatAlert();
  }
  // Unread cue: flicker the CHAT tab for 4 s on every roster event, regardless
  // of which tab is showing. Re-firing resets the timer; cleared early on view.
  let chatAlertTimer = null;
  function bumpChatAlert() {
    $$('[data-drawer="comm"] [data-tab="chat"]').forEach(b => b.classList.add('comm-alert'));
    clearTimeout(chatAlertTimer);
    chatAlertTimer = setTimeout(clearChatAlert, 4000);
  }
  function clearChatAlert() {
    clearTimeout(chatAlertTimer); chatAlertTimer = null;
    $$('[data-drawer="comm"] [data-tab="chat"]').forEach(b => b.classList.remove('comm-alert'));
  }
  function renderReacts(mid) {
    const map = reacts.get(mid);
    const html = map ? Array.from(map.entries())
      .map(([e, n]) => '<span class="comm-react">' + escHtml(e) + (n > 1 ? '<i>' + n + '</i>' : '') + '</span>')
      .join('') : '';
    $$('[data-reacts="' + (window.CSS && CSS.escape ? CSS.escape(mid) : mid) + '"]').forEach(el => el.innerHTML = html);
  }
  function applyReact(mid, emoji) {
    let map = reacts.get(mid);
    if (!map) { map = new Map(); reacts.set(mid, map); }
    // Lobby toy: counts are bare increments, not per-peer sets. Good enough.
    map.set(emoji, (map.get(emoji) || 0) + 1);
    renderReacts(mid);
  }
  function serialMsg(m) { return { id: m.id, peerId: m.self ? selfId : m.peerId, text: m.text, ts: m.ts }; }
  function mergeHistory(arr) {
    if (!Array.isArray(arr) || !arr.length) return;
    const known = new Set(messages.map(m => m.id));
    let added = false;
    arr.forEach(m => {
      if (m && m.id && !known.has(m.id)) {
        messages.push({ id: m.id, peerId: m.peerId, text: String(m.text || ''), ts: m.ts || Date.now(), self: m.peerId === selfId });
        known.add(m.id); added = true;
      }
    });
    if (!added) return;
    messages.sort((a, b) => a.ts - b.ts);
    if (messages.length > 200) messages.splice(0, messages.length - 200);
    renderAllMsgs();
  }

  // ---- Presence ----
  function myPresence() { return { nick, conf: inConference, mic: micOn(), cam: camOn(), screen: screenFlag }; }
  function onPresence(id, d) {
    const p = peers.get(id) || {};
    const seen = p._seen;                 // first presence after join?
    const prevNick = p.nick || '';
    const newNick = (d && d.nick) || '';
    p._seen = true;
    p.nick = newNick;
    p.conf = !!(d && d.conf);
    p.mic = !!(d && d.mic); p.cam = !!(d && d.cam); p.screen = !!(d && d.screen);
    peers.set(id, p);
    if (!seen) {
      // First presence is the reliable "this peer is here" signal — onPeerJoin
      // can fire one-sidedly over the tracker, but presence is always exchanged
      // both ways. Announce the join here, with their name already known.
      addSystemLine(tmpl('sysJoined', '{id} joined').replace('{id}', newNick || shortId(id)));
    } else if (newNick !== prevNick) {
      // Name change — read better with the old name (or id) as the subject.
      addSystemLine(newNick
        ? tmpl('sysNick', '{id} is now {name}').replace('{id}', prevNick || shortId(id)).replace('{name}', newNick)
        : tmpl('sysUnnick', '{id} cleared their name').replace('{id}', prevNick || shortId(id)));
    }
    renderRoster(); updateTile(id);
  }
  function broadcastPresence() { if (act.pres) act.pres(myPresence()); }
  function setNick(v) {
    const prev = nick;
    nick = String(v || '').trim().slice(0, 24);
    try { sessionStorage.setItem('comm:nick', nick); } catch (_) {}
    broadcastPresence();
    renderRoster();
    renderAllMsgs();   // own past lines relabel
    updateTile('self');
    // Local confirmation line (no tab-alert — the user did this themselves).
    if (nick && nick !== prev) addSystemLine(tmpl('sysSelfNick', 'you are now {name}').replace('{name}', nick), false);
  }

  // ---- Room lifecycle ----
  function mkAction(id, onMsg) {
    const a = room.makeAction(id);
    if (onMsg) a.onMessage = onMsg;
    return a.send;
  }
  async function ensureRoom() {
    if (joined) return room;
    joined = true;
    try {
      const t = await loadTrystero();
      const cfg = { appId: 'cisr' };
      if (roomPass) cfg.password = roomPass;
      // appId + room are public (DHT-discoverable). A password additionally
      // encrypts the SDP handshake — that's the private-room knob.
      room = t.joinRoom(cfg, roomName);

      act.chat  = mkAction('chat',  (d, m) => addMessage({ id: d.id, peerId: m.peerId, text: String(d.text || ''), ts: d.ts || Date.now(), self: false }));
      act.hreq  = mkAction('hreq',  (_, m) => act.hres && act.hres({ msgs: messages.filter(x => !x.sys).slice(-50).map(serialMsg) }, { target: m.peerId }));
      act.hres  = mkAction('hres',  (d) => mergeHistory(d && d.msgs));
      act.pres  = mkAction('pres',  (d, m) => onPresence(m.peerId, d));
      act.type  = mkAction('type',  (d, m) => { const p = peers.get(m.peerId); if (p) { p.typing = !!(d && d.on); renderTyping(); } });
      act.react = mkAction('react', (d) => { if (d && d.mid && d.emoji) applyReact(d.mid, d.emoji); });

      room.onPeerJoin = (id) => {
        markReady();                       // a real peer = definitely on the network
        if (!peers.has(id)) peers.set(id, {});
        if (act.pres) act.pres(myPresence(), { target: id });
        // addStream wants a BARE peer id as 2nd arg (not {target}); the torrent
        // build feeds it straight to its peer-list normaliser. {target} here
        // silently targets a phantom peer and nothing is delivered.
        if (inConference && localStream) { try { room.addStream(localStream, id); } catch (_) {} }
        if (!synced) { synced = true; if (act.hreq) act.hreq(1, { target: id }); }
        // Join is announced from the first presence packet (see onPresence) so
        // the line carries the peer's name and survives onPeerJoin asymmetry.
        renderRoster(); updateStatus();
      };
      room.onPeerLeave = (id) => {
        const p = peers.get(id);
        addSystemLine(tmpl('sysLeft', '{id} left').replace('{id}', (p && p.nick) ? p.nick : shortId(id)), false);
        peers.delete(id); removeTile(id); renderRoster(); renderTyping(); updateStatus();
      };
      room.onPeerStream = (s, id) => { const p = peers.get(id) || {}; p.stream = s; peers.set(id, p); attachTile(id, s); attachAnalyser(id, s); updateTile(id); };
      room.onPeerTrack = (track, s, id) => {
        const p = peers.get(id) || {}; p.stream = s; peers.set(id, p);
        attachTile(id, s); updateTile(id);
        if (track.kind === 'audio') attachAnalyser(id, s);
        ['mute', 'unmute', 'ended'].forEach(ev => { try { track.addEventListener(ev, () => updateTile(id)); } catch (_) {} });
      };

      pollRelays();
      watchRelayOffers();   // attach the offer sniff now, not only on the 1s poll
      updateStatus();
      // First render: privacy notice + self-only roster, so the panel reads as
      // "you're in the lobby" immediately rather than blank until a peer shows.
      renderAllMsgs();
      renderRoster();
    } catch (err) {
      console.warn('comm: room init failed', err);
      joined = false;
    }
    return room;
  }
  function teardownRoom() {
    try { leaveConference(); } catch (_) {}
    try { if (room) room.leave(); } catch (_) {}
    room = null; act = {}; joined = false; synced = false;
    peers.clear(); messages.length = 0; reacts.clear();
    myTyping = false; clearTimeout(typingTimer);
    clearInterval(relayTimer); relayTimer = null;
    if (graceTimer) { clearTimeout(graceTimer); graceTimer = null; }
    peerInbound = false; graceTries = 0;
    // networkReady stays sticky across hops — relayOnline() still gates "on".
    clearChatAlert();
    renderAllMsgs(); renderRoster(); renderTyping(); updateStatus();
  }
  function switchRoom(name, pass) {
    const n = String(name || '').trim().toLowerCase().replace(/[^a-z0-9_-]/g, '').slice(0, 32) || 'lobby';
    if (n === roomName && (pass || '') === roomPass) return;
    teardownRoom();
    roomName = n; roomPass = pass || '';
    try { location.hash = n === 'lobby' ? '' : 'comm=' + n; } catch (_) {}
    reflectRoom();
    ensureRoom();
  }
  function reflectRoom() {
    $$('[data-comm-room-label]').forEach(el => el.textContent = roomName + (roomPass ? ' 🔒' : ''));
    $$('[data-comm-room]').forEach(el => { if (el.value !== roomName && el.tagName === 'INPUT') el.value = roomName === 'lobby' ? '' : roomName; });
  }

  // ---- Chat actions ----
  async function sendChat(text) {
    const t = String(text || '').trim();
    if (!t) return;
    if (!joined) await ensureRoom();
    const m = { id: (selfId.slice(0, 4) || 'me') + '-' + (msgSeq++), peerId: selfId, text: t, ts: Date.now(), self: true };
    addMessage(m);
    if (act.chat) act.chat({ id: m.id, text: t, ts: m.ts });
    stopTyping();
  }
  function onComposerInput() {
    if (!act.type) return;
    if (!myTyping) { myTyping = true; act.type({ on: true }); }
    clearTimeout(typingTimer);
    typingTimer = setTimeout(stopTyping, 2500);
  }
  function stopTyping() {
    clearTimeout(typingTimer);
    if (myTyping) { myTyping = false; if (act.type) act.type({ on: false }); }
  }
  function sendReact(mid, emoji) {
    applyReact(mid, emoji);
    if (act.react) act.react({ mid, emoji });
  }

  // ---- Emoji popover (shared: composer insert + message react) ----
  let pop = null;
  function emojiPop() {
    if (pop) return pop;
    pop = document.createElement('div');
    pop.className = 'comm-emoji-pop';
    pop.hidden = true;
    pop.innerHTML = EMOJI.map(e => '<button type="button" data-emoji="' + e + '">' + e + '</button>').join('');
    document.body.appendChild(pop);
    return pop;
  }
  function openEmoji(x, y, mode, mid) {
    const el = emojiPop();
    el.dataset.mode = mode; el.dataset.mid = mid || '';
    el.hidden = false;
    const w = el.offsetWidth || 180, h = el.offsetHeight || 40;
    el.style.left = Math.max(4, Math.min(x, innerWidth - w - 4)) + 'px';
    el.style.top  = Math.max(4, Math.min(y - h - 4, innerHeight - h - 4)) + 'px';
  }
  function closeEmoji() { if (pop) pop.hidden = true; }

  // ---- Conference: media ----
  function micOn() { return !!localStream && localStream.getAudioTracks().some(t => t.enabled); }
  function camOn() { return !!localStream && !screenFlag && localStream.getVideoTracks().some(t => t.enabled); }
  function showGumError(t) { gumErrEls().forEach(el => el.textContent = t || ''); }
  function flipConfView(on) {
    precallEls().forEach(el => el.hidden = on);
    callEls().forEach(el => el.hidden = !on);
  }
  // Reflect real track/share state onto the toolbar buttons (esp. after an
  // audio-only join, where CAM must not look armed).
  function syncMediaBtns() {
    $$('[data-comm-mute-mic]').forEach(b => b.classList.toggle('active', micOn()));
    $$('[data-comm-mute-cam]').forEach(b => b.classList.toggle('active', camOn()));
    $$('[data-comm-screen]').forEach(b => b.classList.toggle('active', screenFlag));
  }

  async function joinConference(wantVideo) {
    if (inConference) return;
    showGumError('');
    try {
      localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: !!wantVideo });
    } catch (e) {
      if (wantVideo) {
        try { localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false }); showGumError(gumErrEls()[0]?.dataset.noCam || 'No camera — joined with audio only.'); }
        catch (e2) { showGumError((gumErrEls()[0]?.dataset.fallback || 'Camera/microphone access denied.') + (e2?.message ? ' — ' + e2.message : '')); return; }
      } else {
        showGumError((gumErrEls()[0]?.dataset.fallback || 'Microphone access denied.') + (e?.message ? ' — ' + e.message : '')); return;
      }
    }
    if (!joined) await ensureRoom();
    if (!room) { stopLocalStream(); return; }
    inConference = true;
    camTrack = localStream.getVideoTracks()[0] || null;
    flipConfView(true);
    attachTile('self', localStream);
    attachAnalyser('self', localStream);
    updateTile('self');
    // Add exactly once per current peer; future peers handled in onPeerJoin.
    // Bare id as 2nd arg — see onPeerJoin note on the addStream signature.
    peers.forEach((_, id) => { try { room.addStream(localStream, id); } catch (_) {} });
    startLevels(); startPings();
    syncMediaBtns();
    broadcastPresence();
  }
  function stopLocalStream() {
    if (localStream) { localStream.getTracks().forEach(t => t.stop()); localStream = null; }
    camTrack = null; screenFlag = false;
  }
  function leaveConference() {
    if (!inConference) { return; }
    inConference = false;
    clearInterval(levelTimer); levelTimer = null;
    clearInterval(pingTimer); pingTimer = null;
    try { if (room && localStream) room.removeStream(localStream); } catch (_) {}
    stopLocalStream();
    localAnalyser = null;
    peers.forEach(p => { p.analyser = null; });
    clearGrid();
    flipConfView(false);
    syncMediaBtns();
    broadcastPresence();
    renderRoster();
  }
  function toggleTrack(kind) {
    if (!localStream) return null;
    const tr = localStream.getTracks().filter(t => t.kind === kind);
    if (!tr.length) return null;
    const next = !tr[0].enabled;
    tr.forEach(t => t.enabled = next);
    broadcastPresence(); updateTile('self'); renderRoster();
    return next;
  }
  async function shareScreen() {
    if (!inConference) return;
    if (screenFlag) { stopScreen(); return; }
    let ds;
    try { ds = await navigator.mediaDevices.getDisplayMedia({ video: true }); } catch (_) { return; }
    const st = ds.getVideoTracks()[0];
    if (!st) return;
    const cur = localStream.getVideoTracks()[0] || null;
    try {
      if (cur) { room.replaceTrack(cur, st); localStream.removeTrack(cur); }
      else room.addTrack(st, localStream);
    } catch (_) {}
    localStream.addTrack(st);
    screenFlag = true;
    st.onended = () => stopScreen();
    updateTile('self'); syncMediaBtns(); broadcastPresence(); renderRoster();
  }
  function stopScreen() {
    if (!screenFlag) return;
    const st = localStream.getVideoTracks()[0] || null;
    try {
      if (st && camTrack) { room.replaceTrack(st, camTrack); localStream.removeTrack(st); localStream.addTrack(camTrack); }
      else if (st) { room.removeTrack(st); localStream.removeTrack(st); }
    } catch (_) {}
    if (st) st.stop();
    screenFlag = false;
    updateTile('self'); broadcastPresence(); renderRoster();
  }

  // ---- Conference: tiles ----
  function tilesFor(id) {
    const sel = 'div[data-tile="' + (window.CSS && CSS.escape ? CSS.escape(id) : id) + '"]';
    return gridEls().flatMap(g => $$(sel, g));
  }
  function attachTile(id, stream) {
    gridEls().forEach(g => {
      let tile = g.querySelector('div[data-tile="' + (window.CSS && CSS.escape ? CSS.escape(id) : id) + '"]');
      if (!tile) {
        tile = document.createElement('div');
        tile.className = 'comm-tile';
        tile.dataset.tile = id;
        const v = document.createElement('video');
        v.autoplay = true; v.playsInline = true;
        if (id === 'self') v.muted = true;   // never echo own mic
        const av = document.createElement('div'); av.className = 'comm-av'; av.textContent = initial(id);
        const lb = document.createElement('span'); lb.className = 'comm-tile-lbl';
        tile.append(v, av, lb);
        g.appendChild(tile);
      }
      const v = tile.querySelector('video');
      if (v && stream && v.srcObject !== stream) v.srcObject = stream;
    });
    updateTile(id);
  }
  function removeTile(id) { tilesFor(id).forEach(t => t.remove()); }
  function clearGrid() { gridEls().forEach(g => { while (g.firstChild) g.removeChild(g.firstChild); }); }
  function hasLiveVideo(id, stream) {
    if (id === 'self') return camOn() || screenFlag;
    const p = peers.get(id);
    if (p && p.cam === false && !(p && p.screen)) return false;
    return !!stream && stream.getVideoTracks().some(t => t.readyState === 'live' && !t.muted);
  }
  function updateTile(id) {
    const p = id === 'self' ? null : peers.get(id);
    const stream = id === 'self' ? localStream : (p && p.stream);
    const live = hasLiveVideo(id, stream);
    const mic = id === 'self' ? micOn() : (p && p.mic);
    const scr = id === 'self' ? screenFlag : (p && p.screen);
    const lat = id !== 'self' && p && p.lat != null ? ' · ' + p.lat + 'ms' : '';
    tilesFor(id).forEach(t => {
      t.classList.toggle('has-video', !!live);
      t.classList.toggle('muted', mic === false);
      const av = t.querySelector('.comm-av'); if (av) av.textContent = initial(id);
      const lb = t.querySelector('.comm-tile-lbl');
      if (lb) lb.textContent = label(id) + (scr ? ' 🖥' : '') + (mic === false ? ' 🔇' : '') + lat;
    });
  }

  // ---- Conference: speaking + latency ----
  function ensureAudioCtx() {
    if (!audioCtx) { const C = window.AudioContext || window.webkitAudioContext; if (C) audioCtx = new C(); }
    if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume().catch(() => {});
    return audioCtx;
  }
  function makeAnalyser(stream) {
    try {
      const ctx = ensureAudioCtx();
      if (!ctx || !stream || !stream.getAudioTracks().length) return null;
      const src = ctx.createMediaStreamSource(stream);
      const an = ctx.createAnalyser(); an.fftSize = 256;
      src.connect(an);
      return an;
    } catch (_) { return null; }
  }
  function attachAnalyser(id, stream) {
    if (id === 'self') { localAnalyser = makeAnalyser(stream); localFreq = localAnalyser ? new Uint8Array(localAnalyser.frequencyBinCount) : null; return; }
    const p = peers.get(id) || {};
    if (!p.analyser) { p.analyser = makeAnalyser(stream); p.freq = p.analyser ? new Uint8Array(p.analyser.frequencyBinCount) : null; peers.set(id, p); }
  }
  function meanLevel(an, buf) {
    if (!an || !buf) return 0;
    an.getByteFrequencyData(buf);
    let s = 0; for (let i = 0; i < buf.length; i++) s += buf[i];
    return s / buf.length;
  }
  function startLevels() {
    clearInterval(levelTimer);
    levelTimer = setInterval(() => {
      setSpeak('self', micOn() && meanLevel(localAnalyser, localFreq) > SPEAK_TH);
      peers.forEach((p, id) => setSpeak(id, p.mic !== false && meanLevel(p.analyser, p.freq) > SPEAK_TH));
    }, 200);
  }
  function setSpeak(id, on) { tilesFor(id).forEach(t => t.classList.toggle('speaking', !!on)); }
  function startPings() {
    clearInterval(pingTimer);
    pingTimer = setInterval(() => {
      if (!room) return;
      peers.forEach((p, id) => {
        try { room.ping(id).then(ms => { p.lat = Math.round(ms); updateTile(id); renderRoster(); }).catch(() => {}); } catch (_) {}
      });
    }, 3000);
  }

  function teardown() { try { teardownRoom(); } catch (_) {} }

  // ---- Wiring ----
  function init() {
    // Pick up a shared room link (#comm=name) before first join.
    try {
      const m = /comm=([a-z0-9_-]{1,32})/i.exec(location.hash || '');
      if (m) roomName = m[1].toLowerCase();
    } catch (_) {}
    reflectRoom();
    updateStatus();   // start in "connecting" — composer disabled until on the network

    document.addEventListener('click', (e) => {
      if (e.target.closest('[data-drawer-toggle="comm"]')) { ensureRoom(); clearChatAlert(); return; }
      // Viewing chat clears the unread flicker (app.js handles the tab switch).
      if (e.target.closest('[data-drawer="comm"] [data-tab="chat"]')) clearChatAlert();

      const reactAdd = e.target.closest('[data-react-add]');
      if (reactAdd) { e.preventDefault(); const r = reactAdd.getBoundingClientRect(); openEmoji(r.left, r.top, 'react', reactAdd.dataset.reactAdd); return; }
      if (e.target.closest('[data-comm-emoji]')) { e.preventDefault(); const b = e.target.closest('[data-comm-emoji]'); const r = b.getBoundingClientRect(); openEmoji(r.left, r.top, 'insert', ''); return; }
      const emo = e.target.closest('[data-emoji]');
      if (emo) {
        e.preventDefault();
        const el = emojiPop();
        const val = emo.dataset.emoji;
        if (el.dataset.mode === 'react' && el.dataset.mid) sendReact(el.dataset.mid, val);
        else { const inp = inputEls()[0]; if (inp) { inp.value += val; inp.focus(); } }
        closeEmoji(); return;
      }
      if (pop && !pop.hidden && !e.target.closest('.comm-emoji-pop')) closeEmoji();

      if (e.target.closest('[data-comm-send]')) { e.preventDefault(); const inp = inputEls()[0]; if (inp) { sendChat(inp.value); inp.value = ''; } return; }
      if (e.target.closest('[data-comm-nick-set]')) { e.preventDefault(); const i = $('[data-comm-nick]'); if (i) setNick(i.value); return; }
      if (e.target.closest('[data-comm-room-go]')) { e.preventDefault(); const i = $('[data-comm-room]'); const pw = $('[data-comm-pass]'); switchRoom(i ? i.value : 'lobby', pw ? pw.value : ''); if (pw) pw.value = ''; return; }

      if (e.target.closest('[data-comm-join-av]'))    { e.preventDefault(); joinConference(true); return; }
      if (e.target.closest('[data-comm-join-audio]')) { e.preventDefault(); joinConference(false); return; }
      if (e.target.closest('[data-comm-leave-conf]')) { e.preventDefault(); leaveConference(); return; }
      if (e.target.closest('[data-comm-screen]'))     { e.preventDefault(); shareScreen(); return; }
      const mic = e.target.closest('[data-comm-mute-mic]');
      if (mic) { e.preventDefault(); const on = toggleTrack('audio'); if (on != null) mic.classList.toggle('active', on); return; }
      const cam = e.target.closest('[data-comm-mute-cam]');
      if (cam) { e.preventDefault(); const on = toggleTrack('video'); if (on != null) cam.classList.toggle('active', on); return; }
    });

    document.addEventListener('input', (e) => { if (e.target.closest('[data-comm-msg-input]')) onComposerInput(); });

    // Enter inside the nick / room mini-forms submits them (the forms carry
    // onsubmit="return false" so the page never reloads).
    document.addEventListener('submit', (e) => {
      const form = e.target;
      if (!form.matches || !form.matches('.comm-fields')) return;
      e.preventDefault();
      if (form.querySelector('[data-comm-nick]')) { const i = $('[data-comm-nick]'); if (i) setNick(i.value); }
      else if (form.querySelector('[data-comm-room]')) { const i = $('[data-comm-room]'); const pw = $('[data-comm-pass]'); switchRoom(i ? i.value : 'lobby', pw ? pw.value : ''); if (pw) pw.value = ''; }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { closeEmoji(); return; }
      if (e.key !== 'Enter' || e.shiftKey) return;
      const inp = e.target.closest('[data-comm-msg-input]');
      if (!inp) return;
      e.preventDefault();
      sendChat(inp.value); inp.value = '';
    });

    addEventListener('beforeunload', teardown);
  }

  window.siteComm = { init, teardown, ensureRoom, sendChat, switchRoom, setNick, joinConference, leaveConference };
  init();
})();
