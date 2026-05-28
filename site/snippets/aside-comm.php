<?php
  /** @var \Kirby\Cms\Site $site */
?>
<button class="drawer-toggle drawer-toggle-comm"
        data-drawer-toggle="comm"
        type="button"
        aria-controls="drawer-comm"
        aria-expanded="false">
  <span aria-hidden="true">▲</span> <?= t('comm.title', 'COMMS') ?>
</button>

<div class="drawer drawer-comm" id="drawer-comm" data-drawer="comm" hidden role="dialog" aria-label="<?= t('comm.region', 'Communications') ?>">
  <div class="drawer-tabs" role="tablist" aria-label="<?= t('comm.tabs', 'Communication tabs') ?>">
    <button type="button" data-tab="chat" class="active" role="tab" aria-selected="true"><?= t('comm.chat', 'CHAT') ?></button>
    <button type="button" data-tab="conf" role="tab" aria-selected="false"><?= t('comm.conf', 'CONF') ?></button>
    <button type="button" data-drawer-close class="drawer-x" aria-label="<?= t('ui.close', 'Close') ?>">✕</button>
  </div>
  <div class="drawer-panels">

    <div data-panel="chat" class="drawer-panel comm-panel comm-panel-chat">
      <div class="comm-head">
        <span class="ui-sku"><?= esc(t('comm.lobby_label', 'Lobby')) ?></span>
        <span class="ui-sku" data-comm-peer-count
              data-fmt="<?= esc(t('comm.peers_n', '{n} peers'), 'attr') ?>"></span>
      </div>
      <ol class="comm-msgs" data-comm-msg-list aria-live="polite"
          data-chat-privacy="<?= esc(t('comm.chat_privacy', 'WebRTC peer connection — your IP is visible to peers in this lobby. Close the tab or refresh to disconnect.'), 'attr') ?>"></ol>
      <form class="comm-composer" onsubmit="return false">
        <textarea data-comm-msg-input
                  rows="2"
                  maxlength="500"
                  aria-label="<?= t('comm.composer_ph', 'Type a message') ?>"
                  placeholder="<?= esc(t('comm.composer_ph', 'Type a message…'), 'attr') ?>"></textarea>
        <button type="submit" class="ui-badge" data-comm-send><?= t('comm.send', 'SEND') ?></button>
      </form>
    </div>

    <div data-panel="conf" class="drawer-panel comm-panel comm-panel-conf" hidden>
      <div class="comm-precall" data-comm-precall>
        <p class="ui-sku"><?= t('comm.privacy_note', 'WebRTC over public trackers. Your IP is visible to peers; camera and mic stay off until you click JOIN.') ?></p>
        <div class="comm-controls">
          <button type="button" class="ui-badge" data-comm-join-conf><?= t('comm.join_conf', 'JOIN CONFERENCE') ?></button>
        </div>
        <div class="ui-sku" data-comm-gum-err
             data-fallback="<?= esc(t('comm.gum_denied', 'Camera/microphone access denied.'), 'attr') ?>"></div>
      </div>
      <div class="comm-call" data-comm-call hidden>
        <div class="comm-grid" data-comm-grid></div>
        <div class="comm-controls">
          <button type="button" class="ui-badge active" data-comm-mute-mic><?= t('comm.mic', 'MIC') ?></button>
          <button type="button" class="ui-badge active" data-comm-mute-cam><?= t('comm.cam', 'CAM') ?></button>
          <button type="button" class="ui-badge" data-comm-leave-conf><?= t('comm.leave_conf', 'LEAVE') ?></button>
        </div>
      </div>
    </div>

  </div>
</div>
