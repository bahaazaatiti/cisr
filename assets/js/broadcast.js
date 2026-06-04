/* Live broadcast — viewer side (thin presenter).
 *
 * Ships only on a live home page (footer gates it on broadcast_active()). The
 * heavy lifting — joining the room, the WebRTC mesh, receiving streams, verifying
 * the broadcaster's signature — is done by comm.js's signedReceiver(), which joins
 * its OWN room (no comms-drawer hijack, no URL change). This module just hands it
 * the baked room + public key and mirrors the verified broadcaster's stream into
 * the hero player.
 *
 * Receiving is purely passive: we send no media and no presence. The broadcaster
 * addStreams to every peer that connects, so simply being in the room delivers the
 * stream; signedReceiver verifies the signed presence and surfaces only the real
 * broadcaster's tile. */
(function () {
  'use strict';
  if (window.siteBroadcast) return;

  var script = document.querySelector('script[data-broadcast-room]');
  if (!script) return;

  var ROOM   = script.dataset.broadcastRoom || '';
  var PUBPEM = script.dataset.broadcastPubkey || '';
  if (!ROOM || !PUBPEM) return;

  // Resolve the hero nodes LIVE on every use, never cache them. The home page
  // re-renders its content (full load + app.js SPA bootstrap), so a reference
  // captured at script-load time goes stale/detached — writing a stream to it
  // silently does nothing while the on-screen node stays empty. Always query the
  // current DOM.
  function host()   { return document.querySelector('[data-broadcast]'); }
  function video()  { var h = host(); return h && h.querySelector('[data-broadcast-video]'); }
  function waitEl() { var h = host(); return h && h.querySelector('[data-broadcast-wait]'); }
  function unmuteEl(){ var h = host(); return h && h.querySelector('[data-broadcast-unmute]'); }

  function setState(s) { var h = host(); if (h) h.dataset.state = s; }

  // comm.js hands us the verified broadcaster's MediaStream (or null when it goes
  // away). Mirror it into the hero player; autoplay muted (browsers block unmuted
  // autoplay), reveal tap-to-unmute.
  function onStream(stream) {
    var v = video(), w = waitEl(), u = unmuteEl();
    if (!v) return;
    if (stream) {
      if (v.srcObject !== stream) v.srcObject = stream;
      v.muted = true;
      var p = v.play(); if (p && p.catch) p.catch(function () {});
      setState('live');
      if (w) w.hidden = true;
      if (u) u.hidden = false;
    } else {
      v.srcObject = null;
      setState('waiting');
      if (w) w.hidden = false;
      if (u) u.hidden = true;
    }
  }

  // Tap-to-unmute — delegated so it survives the hero node being re-rendered.
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-broadcast-unmute]')) return;
    var v = video(); if (!v) return;
    v.muted = false;
    var p = v.play(); if (p && p.catch) p.catch(function () {});
    var u = unmuteEl(); if (u) u.hidden = true;
  });

  setState('waiting');

  // comm.js loads lazily (its own deferred script). Wait for its API, then start
  // watching. It owns the room + verification; we only present.
  function begin() {
    if (!window.siteComm || !window.siteComm.signedReceiver) return false;
    window.siteBroadcast = { teardown: function () { onStream(null); } };
    window.siteComm.signedReceiver(ROOM, PUBPEM, { onStream: onStream })
      .then(function (r) { if (r) window.siteBroadcast.handle = r; });
    return true;
  }
  if (!begin()) {
    var tries = 0;
    var t = setInterval(function () { if (begin() || ++tries > 50) clearInterval(t); }, 100);
  }
})();
