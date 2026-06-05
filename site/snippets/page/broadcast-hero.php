<?php
  // Live broadcast hero — only when a broadcast is on (toggle + room + pubkey).
  // Two accent marquee frames (reusing the ticker's CSS-only crawl) bracket a
  // 16:9 player. assets/js/broadcast.js (loaded at the END of this snippet, so it
  // ships only on the live home page — not site-wide) joins the room receive-only
  // and fills the <video> once it verifies the broadcaster's signature.
  if (!broadcast_active()) return;

  // Mode-aware privacy line — honest about whether the relay actually hides IPs.
  $warn = broadcast_relay()
    ? t('bc.relayed',    'CONNECTION RELAYED · IP HIDDEN')
    : t('bc.ip_visible', 'YOUR IP IS VISIBLE VIA WEBRTC');

  // A marquee edge: one line repeated, carried twice so the crawl loops
  // seamlessly (second copy inert + aria-hidden, like the ticker). $dir picks the
  // scroll axis/direction class so the four edges flow AROUND the player:
  // top →, right ↓, bottom ←, left ↑.
  $edge = function (string $text, string $dir) {
    // Each edge repeats the text a few times so a long thin side stays filled.
    $seg = '';
    for ($i = 0; $i < 4; $i++) {
      $seg .= '<span class="tk-item"><span class="tk-dot" aria-hidden="true"></span>'
            . '<span class="tk-text">' . esc($text) . '</span></span>';
    }
  ?>
    <div class="bc-edge bc-edge-<?= $dir ?>" aria-hidden="true">
      <div class="tk-track">
        <div class="tk-seg"><?= $seg ?></div>
        <div class="tk-seg" aria-hidden="true" inert><?= $seg ?></div>
      </div>
    </div>
  <?php };
?>
<section class="bc" data-broadcast data-state="waiting"
         aria-label="<?= esc(t('bc.region', 'Live broadcast'), 'attr') ?>"
         data-relay-unavailable="<?= esc(t('bc.relay_unavailable', 'Relay unavailable — broadcast hidden.'), 'attr') ?>">
  <?php
    $brk = t('bc.live_broadcast', 'BREAKING · LIVE BROADCAST');
    // Text travels around the perimeter: top + right carry the headline, bottom +
    // left carry the privacy warning — so reading clockwise alternates the two.
    $edge($brk, 'top');
    $edge($warn, 'right');
    $edge($warn, 'bottom');
    $edge($brk, 'left');
  ?>
  <div class="bc-stage vid-frame">
    <video class="bc-video" data-broadcast-video playsinline muted></video>
    <div class="bc-wait ui-sku" data-broadcast-wait><?= t('bc.waiting', 'WAITING FOR SIGNAL') ?></div>
    <button type="button" class="ui-badge bc-unmute" data-broadcast-unmute hidden><?= t('bc.unmute', 'TAP TO UNMUTE') ?></button>
  </div>
</section>
<?php
  // Viewer script lives HERE (inside #panel), not the footer — so broadcast.min.js
  // + the trystero pull + WebRTC happen ONLY on the live home page, never on inner
  // pages with no hero. app.js re-executes #panel scripts on SPA-nav, so it
  // (re)starts when you navigate home. comm.js (footer, all pages) provides the
  // signedReceiver it calls. Config rides on the data-* attributes.
  $commRelays = implode(' ', trackers_list('relays') ?: (array) option('comm.relays', []));
?>
<script src="<?= url('assets/js/broadcast.min.js') ?>"
        data-comm-vendor="<?= url('assets/js/vendor/trystero.min.js') ?>"
        data-broadcast-room="<?= esc(broadcast_room(), 'attr') ?>"
        data-broadcast-pubkey="<?= esc(broadcast_pubkey(), 'attr') ?>"
        data-broadcast-relay="<?= broadcast_relay() ? '1' : '0' ?>"
        data-broadcast-relays="<?= esc($commRelays, 'attr') ?>"
        data-broadcast-turn="<?= esc(implode('|;|', trackers_list('turn')), 'attr') ?>"
        defer></script>
