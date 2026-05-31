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
    <span class="comm-conn" data-comm-conn data-state="off"
          data-online="<?= esc(t('comm.online', 'connected'), 'attr') ?>"
          data-offline="<?= esc(t('comm.offline', 'connecting…'), 'attr') ?>"
          aria-hidden="true"></span>
    <button type="button" data-drawer-close class="drawer-x" aria-label="<?= t('ui.close', 'Close') ?>">✕</button>
  </div>
  <div class="drawer-panels">

    <div data-panel="chat" class="drawer-panel comm-panel comm-panel-chat">
      <div class="comm-head">
        <span class="ui-sku comm-room-tag" data-comm-room-label>lobby</span>
        <span class="ui-sku" data-comm-peer-count
              data-fmt="<?= esc(t('comm.peers_n', '{n} peers'), 'attr') ?>"
              data-connecting="<?= esc(t('comm.connecting', 'connecting…'), 'attr') ?>"
              data-searching="<?= esc(t('comm.searching', 'searching for peers…'), 'attr') ?>"></span>
      </div>

      <details class="comm-meta">
        <summary class="ui-sku"><?= t('comm.who', 'WHO’S HERE') ?></summary>
        <ul class="comm-roster" data-comm-roster aria-live="polite"></ul>
        <form class="comm-fields" onsubmit="return false">
          <input class="comm-in" data-comm-nick name="comm-nick" autocomplete="off" maxlength="24"
                 aria-label="<?= t('comm.nick', 'Nickname') ?>"
                 placeholder="<?= esc(t('comm.nick_ph', 'nickname (optional)'), 'attr') ?>">
          <button type="submit" class="ui-badge" data-comm-nick-set><?= t('comm.nick_set', 'SET') ?></button>
        </form>
        <form class="comm-fields" onsubmit="return false">
          <input class="comm-in" data-comm-room name="comm-room" autocomplete="off" maxlength="32"
                 aria-label="<?= t('comm.room', 'Room') ?>"
                 placeholder="<?= esc(t('comm.room_ph', 'room (blank = lobby)'), 'attr') ?>">
          <input class="comm-in" data-comm-pass name="comm-pass" type="password" autocomplete="new-password" maxlength="64"
                 aria-label="<?= t('comm.pass', 'Room password') ?>"
                 placeholder="<?= esc(t('comm.pass_ph', 'password (optional)'), 'attr') ?>">
          <button type="submit" class="ui-badge" data-comm-room-go><?= t('comm.room_go', 'GO') ?></button>
        </form>
        <p class="ui-sku comm-room-hint"><?= t('comm.room_hint', 'A password makes the room private (encrypts the WebRTC handshake). Share the link to invite.') ?></p>
      </details>

      <ol class="comm-msgs" data-comm-msg-list aria-live="polite"
          data-chat-privacy="<?= esc(t('comm.chat_privacy', 'WebRTC peer connection — your IP is visible to peers in this lobby. Close the tab or refresh to disconnect.'), 'attr') ?>"
          data-sys-joined="<?= esc(t('comm.sys_joined', '{id} joined'), 'attr') ?>"
          data-sys-left="<?= esc(t('comm.sys_left', '{id} left'), 'attr') ?>"
          data-sys-nick="<?= esc(t('comm.sys_nick', '{id} is now {name}'), 'attr') ?>"
          data-sys-unnick="<?= esc(t('comm.sys_unnick', '{id} cleared their name'), 'attr') ?>"
          data-sys-self-nick="<?= esc(t('comm.sys_self_nick', 'you are now {name}'), 'attr') ?>"
          data-typing-one="<?= esc(t('comm.typing_one', '{name} is typing…'), 'attr') ?>"
          data-typing-many="<?= esc(t('comm.typing_many', '{n} people are typing…'), 'attr') ?>"></ol>
      <div class="ui-sku comm-typing" data-comm-typing aria-live="polite"></div>

      <form class="comm-composer" onsubmit="return false">
        <button type="button" class="comm-emoji-btn" data-comm-emoji aria-label="<?= t('comm.emoji', 'Emoji') ?>">☺</button>
        <textarea data-comm-msg-input
                  name="comm-msg" autocomplete="off"
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
          <button type="button" class="ui-badge" data-comm-join-av><?= t('comm.join_av', 'JOIN · CAM + MIC') ?></button>
          <button type="button" class="ui-badge" data-comm-join-audio><?= t('comm.join_audio', 'JOIN · AUDIO ONLY') ?></button>
        </div>
        <div class="ui-sku" data-comm-gum-err
             data-fallback="<?= esc(t('comm.gum_denied', 'Camera/microphone access denied.'), 'attr') ?>"
             data-no-cam="<?= esc(t('comm.gum_no_cam', 'No camera — joined with audio only.'), 'attr') ?>"></div>
      </div>
      <div class="comm-call" data-comm-call hidden>
        <div class="comm-grid" data-comm-grid></div>
        <div class="comm-controls">
          <button type="button" class="ui-badge active" data-comm-mute-mic><?= t('comm.mic', 'MIC') ?></button>
          <button type="button" class="ui-badge active" data-comm-mute-cam><?= t('comm.cam', 'CAM') ?></button>
          <button type="button" class="ui-badge" data-comm-screen><?= t('comm.screen', 'SCREEN') ?></button>
          <button type="button" class="ui-badge" data-comm-leave-conf><?= t('comm.leave_conf', 'LEAVE') ?></button>
        </div>
      </div>
    </div>

  </div>
</div>
