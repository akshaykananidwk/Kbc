/**
 * Audience display screen.
 *
 * Reads only /api/display/state, which is sanitised server-side: the correct
 * answer simply is not in the payload until the operator reveals the result.
 */
(function () {
  'use strict';

  var root = document.getElementById('dRoot');
  if (!root) return;

  var config = JSON.parse(root.getAttribute('data-config'));
  var state = JSON.parse(document.getElementById('dState').textContent);
  var settings = state.settings || {};

  var lastVersion = -1;
  var lastState = '';
  var lastLevel = -1;
  var lastLifelineSignature = '';
  var polling = false;
  var failures = 0;

  function esc(value) {
    var div = document.createElement('div');
    div.textContent = value === null || value === undefined ? '' : String(value);
    return div.innerHTML;
  }

  function el(id) { return document.getElementById(id); }

  /* --- Sound and music ---------------------------------------------------- */
  var audio = new window.QuizAudio({
    sounds: settings.sounds || {},
    music: settings.music || {},
    soundEnabled: settings.sound !== false,
    musicEnabled: settings.music_enabled !== false,
    synthFallback: settings.synth_fallback !== false,
    soundVolume: settings.sound_volume || 80,
    musicVolume: settings.music_volume || 25,
    duckOnEffect: settings.duck_on_effect !== false,
    introLoop: settings.intro_loop !== false
  });

  function play(name) { audio.play(name); }

  // Until the TV browser has been clicked once, autoplay is blocked. Show a
  // one-time hint so the operator knows to click the screen.
  audio.onUnlock = function () {
    var hint = el('dAudioHint');
    if (hint) hint.hidden = true;
    applyMusicBed();
    scheduleFit();
    checkForNewBuild();
  };

  /** Chooses which music bed suits the current game state. */
  function applyMusicBed() {
    if (!audio.isUnlocked()) return;
    var s = state.state;

    if (!state.has_game || (!state.question && !state.is_finished)) {
      audio.playBed('intro');
      return;
    }
    if (state.is_finished) {
      audio.playBed(state.prize && parseFloat(state.prize.final_prize) > 0 ? 'victory' : 'intro');
      return;
    }
    if (s === 'ANSWER_LOCKED') { audio.playBed('suspense'); return; }
    audio.playBed('background');
  }

  /* --- Timer -------------------------------------------------------------- */
  var timerRemaining = 0;
  var timerTotal = 0;
  var timerRunning = false;
  var timerSyncedAt = 0;
  var lastTickSecond = -1;
  var chequeShownFor = null;

  function syncTimer() {
    timerRemaining = state.timer ? state.timer.remaining_ms : 0;
    timerTotal = state.timer ? state.timer.total_ms : 0;
    timerRunning = !!(state.timer && state.timer.running);
    timerSyncedAt = Date.now();
  }

  function paintTimer() {
    var remaining = timerRunning
      ? Math.max(0, timerRemaining - (Date.now() - timerSyncedAt))
      : timerRemaining;
    var seconds = Math.ceil(remaining / 1000);
    var ratio = timerTotal > 0 ? remaining / timerTotal : 0;

    var label = el('dTimerLabel');
    var barLabel = el('dTimerBarLabel');
    if (label) label.textContent = seconds < 0 ? '0' : String(seconds);
    if (barLabel) barLabel.textContent = (seconds < 0 ? 0 : seconds) + 's';

    var ring = el('dRing');
    var value = el('dRingValue');
    if (ring && value) {
      var circumference = 2 * Math.PI * 45;
      value.style.strokeDasharray = String(circumference);
      value.style.strokeDashoffset = String(circumference * (1 - ratio));
      ring.className = 'd-ring' + (ratio <= 0.17 ? ' is-danger' : (ratio <= 0.34 ? ' is-warn' : ''));
    }

    var fill = el('dTimerFill');
    if (fill) fill.style.width = (ratio * 100) + '%';

    // Audible countdown over the last five seconds.
    if (timerRunning && seconds !== lastTickSecond && seconds > 0 && seconds <= 5) {
      lastTickSecond = seconds;
      play('tick');
    }
    if (!timerRunning || seconds > 5) { lastTickSecond = -1; }
  }

  /* --- Rendering ---------------------------------------------------------- */

  /* --- Fit to the screen ---------------------------------------------------
   * Every size on this screen is a multiple of --u. This routine chooses the
   * multiplier so that whatever is on air right now fills the screen it is
   * shown on, without ever spilling over the edge. It is what lets the same
   * screen work on a 4K TV, a 16:9 projector and a short laptop panel.
   * ------------------------------------------------------------------------ */
  var FIT_MIN = 0.55;
  var FIT_MAX = 1.45;
  var fitScheduled = false;
  var currentFit = 1;
  var lastSignature = '';

  /**
   * Measurement must see the real layout, not the decorative scaling of a
   * selected or revealed option: a highlighted option is 5% wider than its
   * box, which used to read as "this does not fit" and shrank the whole
   * screen the instant an answer was revealed.
   */
  function measure(fn) {
    var stage = document.getElementById('dRoot');
    if (stage) { stage.classList.add('is-measuring'); }
    try {
      return fn();
    } finally {
      if (stage) { stage.classList.remove('is-measuring'); }
    }
  }

  /** The boxes that make up the game board (never the overlays). */
  function boardBoxes() {
    var boxes = [];
    var main = el('dMain');
    var welcome = el('dWelcome');

    if (main && !main.hidden) {
      boxes.push(el('dCentre') || main);
      // Measured separately: these clip their own overflow, so a long
      // question would otherwise never register as too big.
      var question = document.querySelector('.d-question');
      if (question) boxes.push(question);
      var options = el('dOptions');
      if (options) boxes.push(options);
    }
    if (welcome && !welcome.hidden) boxes.push(welcome);
    return boxes;
  }

  // A few pixels of slack: sub-pixel line boxes make a box that fits
  // perfectly well report one or two pixels of overflow.
  var SLACK = 4;

  function boxesFit(boxes) {
    for (var i = 0; i < boxes.length; i++) {
      var box = boxes[i];
      if (box.scrollHeight > box.clientHeight + SLACK) return false;
      if (box.scrollWidth > box.clientWidth + SLACK) return false;
    }
    return true;
  }

  function setFit(value) {
    currentFit = value;
    document.documentElement.style.setProperty('--d-fit', String(value));
  }

  /**
   * What is on the screen, in the terms that decide how big it can be.
   * Selecting, locking or revealing an answer does not change this, so the
   * board keeps exactly the size it had - no twitch mid-question.
   */
  function layoutSignature() {
    var question = state.question || {};
    var options = question.options || {};
    var count = 0;
    for (var key in options) { if (options[key] !== null) count++; }

    return [
      window.innerWidth, window.innerHeight,
      el('dMain') && !el('dMain').hidden ? 'game' : 'idle',
      (question.text || '').length,
      count,
      question.image ? 'i' : '', question.video ? 'v' : '', question.audio ? 'a' : '',
      (state.lifelines || []).length,
      state.poll && state.poll.status === 'open' ? 'poll' : '',
      config.showLadder ? (state.ladder || []).length : 0
    ].join('|');
  }

  /** Shrinks only the prize ladder until its rows fit their column. */
  function fitLadder() {
    var ladder = el('dLadder');
    if (!ladder || ladder.hidden || !ladder.parentNode || ladder.offsetParent === null) return;

    var value = 1;
    document.documentElement.style.setProperty('--d-ladder-fit', '1');
    for (var step = 0; step < 10; step++) {
      if (ladder.scrollHeight <= ladder.clientHeight + SLACK && ladder.scrollWidth <= ladder.clientWidth + SLACK) break;
      value -= 0.07;
      if (value < 0.45) { document.documentElement.style.setProperty('--d-ladder-fit', '0.45'); break; }
      document.documentElement.style.setProperty('--d-ladder-fit', value.toFixed(3));
    }
  }

  /** Full-screen panels size themselves, independently of the board. */
  function fitOverlay() {
    var overlay = null;
    var all = document.querySelectorAll('.d-overlay');
    for (var i = 0; i < all.length; i++) {
      if (!all[i].hidden) { overlay = all[i]; break; }
    }
    document.documentElement.style.setProperty('--d-overlay-fit', '1');
    if (!overlay) return;

    var value = 1;
    for (var step = 0; step < 12; step++) {
      if (overlay.scrollHeight <= overlay.clientHeight + SLACK && overlay.scrollWidth <= overlay.clientWidth + SLACK) break;
      value -= 0.07;
      if (value < 0.4) { document.documentElement.style.setProperty('--d-overlay-fit', '0.4'); break; }
      document.documentElement.style.setProperty('--d-overlay-fit', value.toFixed(3));
    }
  }

  function fitToScreen(force) {
    fitScheduled = false;

    var signature = layoutSignature();
    var boardChanged = signature !== lastSignature;
    lastSignature = signature;

    measure(function () {
      fitOverlay();

      if (!boardChanged && !force) {
        // Nothing that affects size has changed. Re-measure only to catch a
        // board that no longer fits; otherwise leave it exactly as it is, so
        // selecting an answer never resizes the screen.
        document.documentElement.style.setProperty('--d-ladder-fit', '1');
        if (boxesFit(boardBoxes())) { fitLadder(); return; }
      }

      document.documentElement.style.setProperty('--d-ladder-fit', '1');

      // Grow first: on a big screen the show should use the whole panel.
      setFit(FIT_MAX);
      if (boxesFit(boardBoxes())) { fitLadder(); return; }

      // Otherwise binary-search the largest multiplier that still fits.
      var low = FIT_MIN, high = FIT_MAX, best = FIT_MIN;
      for (var step = 0; step < 9; step++) {
        var mid = (low + high) / 2;
        setFit(mid);
        if (boxesFit(boardBoxes())) { best = mid; low = mid; } else { high = mid; }
      }
      setFit(best);
      fitLadder();
    });
  }

  function scheduleFit(force) {
    if (fitScheduled) return;
    fitScheduled = true;
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () { fitToScreen(force); });
    });
  }

  function forceFit() {
    lastSignature = '';
    scheduleFit(true);
  }

  window.addEventListener('resize', forceFit);
  window.addEventListener('orientationchange', forceFit);
  document.addEventListener('fullscreenchange', forceFit);
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(forceFit).catch(function () {});
  }

  /* --- Refresh after an update ---------------------------------------------
   * The screen is left running for hours. When the server starts reporting a
   * different build, reload once so the new stylesheet and script are used -
   * but never in the middle of a running clock.
   * ------------------------------------------------------------------------ */
  var bootVersion = (state.settings && state.settings.app_version) || '';
  var reloadPending = false;

  function checkForNewBuild() {
    var current = (state.settings && state.settings.app_version) || '';
    if (!current) return;

    // The version arrives with the first poll, not always with the page.
    if (!bootVersion) { bootVersion = current; return; }
    if (current === bootVersion) return;

    reloadPending = true;
    if (timerRunning) return;   // wait for the question to finish

    window.setTimeout(function () { window.location.reload(); }, 400);
  }

  function render() {
    syncTimer();
    settings = state.settings || settings;

    var hasQuestion = !!state.question;
    var showWelcome = !state.has_game || (!hasQuestion && !state.is_finished);

    el('dWelcome').hidden = !showWelcome;
    el('dMain').hidden = showWelcome;
    el('dHeadRight').style.visibility = showWelcome ? 'hidden' : '';

    // Participant
    var participant = state.participant || {};
    setText('dParticipantName', participant.name || '');
    setText('dParticipantMeta', [participant.reg_no, participant.city].filter(Boolean).join(' · '));
    var photo = el('dParticipantPhoto');
    if (photo) {
      photo.innerHTML = participant.photo
        ? '<img src="' + esc(participant.photo) + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:calc(1.4 * var(--u))">'
        : esc((participant.name || '?').charAt(0));
    }
    var participantBox = el('dParticipant');
    if (participantBox) participantBox.style.display = participant.name ? '' : 'none';

    // Header prize
    setText('dQuestionNo', state.level > 0 ? 'Question ' + state.level + ' of ' + state.total_levels : '');
    setText('dAmount', state.prize ? state.prize.current_label : '');
    var giftEl = el('dAmountGift');
    if (giftEl) {
      giftEl.textContent = state.prize && state.prize.gift_name ? '+ ' + state.prize.gift_name : '';
      giftEl.style.display = state.prize && state.prize.gift_name ? '' : 'none';
    }

    // Question + options
    setText('dQuestion', hasQuestion ? state.question.text : '');
    renderQuestionMedia(hasQuestion ? state.question : null);
    renderIdle();
    renderVotePanel();
    renderOptions();
    renderLadder();
    renderLifelines();
    renderOverlay();
    paintTimer();

    audio.update({
      sounds: settings.sounds,
      music: settings.music,
      sound: settings.sound,
      music_enabled: settings.music_enabled,
      music_volume: settings.music_volume,
      sound_volume: settings.sound_volume
    });
    applyMusicBed();

    // Re-measure on every change: a longer question, a fifth option or a
    // revealed answer all change how much has to fit on the screen.
    scheduleFit();
    checkForNewBuild();
  }

  /**
   * Shows whatever media the question carries. Audio and video are played
   * automatically once the screen has been unlocked; an image just shows.
   */
  var mediaKey = '';
  function renderQuestionMedia(question) {
    var box = el('dQuestionMedia');
    if (!box) return;

    var key = question ? [question.id, question.image, question.audio, question.video].join('|') : '';
    if (key === mediaKey) return;
    mediaKey = key;

    box.innerHTML = '';
    if (!question) { box.hidden = true; return; }

    var shown = false;

    if (question.video) {
      var video = document.createElement('video');
      video.className = 'd-question__video';
      video.src = question.video;
      video.controls = false;
      video.playsInline = true;
      video.muted = false;
      box.appendChild(video);
      if (audio.isUnlocked()) {
        var p = video.play();
        if (p && p.catch) p.catch(function () { video.controls = true; });
      } else {
        video.controls = true;
      }
      shown = true;
    } else if (question.image) {
      var img = document.createElement('img');
      img.className = 'd-question__image';
      img.src = question.image;
      img.alt = '';
      box.appendChild(img);
      shown = true;
    }

    if (question.audio) {
      var player = document.createElement('audio');
      player.className = 'd-question__audio';
      player.src = question.audio;
      player.controls = true;
      box.appendChild(player);
      if (audio.isUnlocked()) {
        var ap = player.play();
        if (ap && ap.catch) ap.catch(function () {});
      }
      shown = true;
    }

    box.hidden = !shown;
  }

  function setText(id, value) {
    var node = el(id);
    if (node) node.textContent = value === null || value === undefined ? '' : String(value);
  }

  /* --- Idle screen: hall of fame and sponsors ----------------------------- */
  var idlePanels = [];
  var idleIndex = 0;
  var idleTimer = null;
  var idleSignature = '';

  function renderIdle() {
    var box = el('dIdlePanels');
    if (!box) return;

    var idle = state.idle || { active: false, leaderboard: [], sponsors: [] };
    var boardPanel = el('dLeaderboardPanel');
    var sponsorPanel = el('dSponsorPanel');

    var hasBoard = idle.active && settings.show_leaderboard !== false && (idle.leaderboard || []).length > 0;
    var hasSponsors = idle.active && settings.show_sponsors !== false && (idle.sponsors || []).length > 0;

    if (!hasBoard && !hasSponsors) {
      box.hidden = true;
      stopIdleRotation();
      return;
    }
    box.hidden = false;

    // Only rebuild when the content actually changed.
    var signature = JSON.stringify([hasBoard, hasSponsors, (idle.leaderboard || []).length, (idle.sponsors || []).length,
      (idle.leaderboard || []).map(function (r) { return r.name + r.prize; }).join(',')]);

    if (signature !== idleSignature) {
      idleSignature = signature;

      if (hasBoard) {
        var list = el('dLeaderboard');
        list.innerHTML = '';
        idle.leaderboard.forEach(function (row) {
          var li = document.createElement('li');
          li.innerHTML =
            '<span class="d-board__rank">' + row.rank + '</span>' +
            (row.photo ? '<img class="d-board__photo" src="' + esc(row.photo) + '" alt="">' : '') +
            '<span><span class="d-board__name">' + esc(row.name) + '</span>' +
            (row.city ? '<span class="d-board__city"> · ' + esc(row.city) + '</span>' : '') + '</span>' +
            '<span class="d-board__prize">' + esc(row.prize_label) + '</span>' +
            '<span class="d-board__questions">' + row.questions + ' ✓</span>';
          list.appendChild(li);
        });

        var summary = idle.summary;
        setText('dBoardSummary', summary
          ? summary.games + ' games played · ' + summary.players + ' players · ' +
            summary.total_prize_label + ' awarded'
          : '');
      }

      if (hasSponsors) {
        var wrap = el('dSponsors');
        wrap.innerHTML = '';
        idle.sponsors.forEach(function (sponsor) {
          var node = document.createElement('div');
          node.className = 'd-sponsor' + (sponsor.tier === 'title' ? ' d-sponsor--title' : '');
          node.innerHTML =
            (sponsor.logo ? '<img src="' + esc(sponsor.logo) + '" alt="">' : '') +
            '<span class="d-sponsor__name">' + esc(sponsor.name) + '</span>' +
            (sponsor.tagline ? '<span class="d-sponsor__tagline">' + esc(sponsor.tagline) + '</span>' : '');
          wrap.appendChild(node);
        });
      }

      idlePanels = [];
      if (hasBoard) idlePanels.push(boardPanel);
      if (hasSponsors) idlePanels.push(sponsorPanel);
      idleIndex = 0;
      startIdleRotation();
    }
  }

  function showIdlePanel(index) {
    idlePanels.forEach(function (panel, i) { panel.hidden = i !== index; });
  }

  function startIdleRotation() {
    stopIdleRotation();
    if (idlePanels.length === 0) return;
    showIdlePanel(0);
    if (idlePanels.length === 1) return;

    var every = Math.max(3000, settings.idle_rotate_ms || 9000);
    idleTimer = window.setInterval(function () {
      idleIndex = (idleIndex + 1) % idlePanels.length;
      showIdlePanel(idleIndex);
    }, every);
  }

  function stopIdleRotation() {
    if (idleTimer) { window.clearInterval(idleTimer); idleTimer = null; }
  }

  function renderOptions() {
    var box = el('dOptions');
    if (!box) return;
    box.innerHTML = '';
    if (!state.question) return;

    var options = state.question.options || {};
    var removed = state.question.removed || [];
    var revealed = state.answer.revealed;
    // "correct" is null in the payload until the operator reveals the result.
    var correct = state.answer.correct;
    var selected = state.answer.selected;

    ['A', 'B', 'C', 'D'].forEach(function (key) {
      var text = options[key];
      var node = document.createElement('div');
      node.className = 'd-option';
      if (removed.indexOf(key) !== -1 || text === null) node.classList.add('is-removed');
      if (selected === key && !revealed) node.classList.add('is-selected');
      if (selected === key && state.answer.locked && !revealed) node.classList.add('is-locked');
      if (revealed && correct && key === correct) node.classList.add('is-correct');
      if (revealed && selected === key && correct && key !== correct) node.classList.add('is-wrong');
      if (revealed && correct && key !== correct && selected !== key) node.classList.add('is-dim');

      node.innerHTML = '<span class="d-option__key">' + key + '</span><span>' + esc(text || '') + '</span>';
      box.appendChild(node);
    });
  }

  function renderLadder() {
    var box = el('dLadder');
    if (!box) return;
    if (!config.showLadder) { box.style.display = 'none'; return; }

    box.innerHTML = '';
    (state.ladder || []).slice().reverse().forEach(function (level) {
      var row = document.createElement('div');
      row.className = 'd-ladder__row' +
        (level.is_current ? ' is-current' : '') +
        (level.is_won && !level.is_current ? ' is-won' : '') +
        (level.guaranteed ? ' is-guaranteed' : '');
      row.innerHTML =
        '<span class="d-ladder__no">' + level.level + '</span>' +
        '<span class="d-ladder__amount">' + esc(level.label) + '</span>';
      box.appendChild(row);
    });

    var current = box.querySelector('.is-current');
    if (current && current.scrollIntoView) {
      current.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  }

  /* --- Live audience voting panel ----------------------------------------- */
  var voteQrCode = '';
  var voteSecondsLeft = 0;
  var voteSyncedAt = 0;
  var voteWindow = 0;

  function renderVotePanel() {
    var panel = el('dVotePanel');
    if (!panel) return;

    var poll = state.poll;
    if (!poll || poll.status !== 'open' || settings.show_qr_poll === false) {
      panel.hidden = true;
      voteQrCode = '';
      return;
    }
    panel.hidden = false;

    setText('dVoteCode', poll.code);
    setText('dVoteCount', poll.total_votes);

    // The QR only changes when the poll does, so fetch it once.
    if (poll.code !== voteQrCode) {
      voteQrCode = poll.code;
      voteWindow = Math.max(1, poll.closes_in);
      var box = el('dVoteQr');
      if (box) {
        box.innerHTML = '';
        fetch(poll.qr_url, { credentials: 'same-origin' })
          .then(function (r) { return r.text(); })
          .then(function (svg) {
            if (voteQrCode === poll.code) { box.innerHTML = svg; }
          }).catch(function () { /* the printed code still works */ });
      }
    }

    voteSecondsLeft = poll.closes_in;
    voteSyncedAt = Date.now();
  }

  function paintVoteCountdown() {
    var panel = el('dVotePanel');
    if (!panel || panel.hidden) return;

    var elapsed = Math.floor((Date.now() - voteSyncedAt) / 1000);
    var left = Math.max(0, voteSecondsLeft - elapsed);
    setText('dVoteSeconds', left);

    var fill = el('dVoteFill');
    if (fill && voteWindow > 0) { fill.style.width = ((left / voteWindow) * 100) + '%'; }
    if (left === 0) { panel.hidden = true; }
  }

  function renderLifelines() {
    var box = el('dLifelines');
    if (!box) return;
    if (!config.showLifelines) { box.style.display = 'none'; return; }

    box.innerHTML = '';
    (state.lifelines || []).forEach(function (lifeline) {
      var node = document.createElement('div');
      node.className = 'd-lifeline' + (lifeline.available ? '' : ' is-used');
      node.textContent = lifeline.name;
      box.appendChild(node);
    });
  }

  /* --- Fastest Finger First ------------------------------------------------ */
  var fffSyncedAt = 0;
  var fffRemaining = 0;
  var fffSignature = '';

  function renderFff() {
    var overlay = el('dFffOverlay');
    if (!overlay) return false;

    var fff = state.fff;
    if (!fff || fff.status === 'pending') {
      // A pending round is not yet on air - keep the normal screen.
      if (!fff) { overlay.hidden = true; fffSignature = ''; return false; }
    }

    overlay.hidden = false;

    var lines = String(fff.question || '').split('\n');
    setText('dFffQuestion', lines[0] || '');

    var signature = fff.id + '|' + fff.status + '|' + fff.state_version;
    if (signature !== fffSignature) {
      fffSignature = signature;

      var itemBox = el('dFffItems');
      if (itemBox) {
        itemBox.innerHTML = '';
        var answerOrder = fff.correct_order ? fff.correct_order.split('') : [];

        lines.slice(1).forEach(function (line) {
          var match = /^\s*([ABCD])[\.\)]\s*(.+)$/.exec(line);
          if (!match) return;
          var key = match[1].toUpperCase();
          var position = answerOrder.indexOf(key);

          var node = document.createElement('div');
          node.className = 'd-fff__item' + (position !== -1 ? ' is-answer' : '');
          node.innerHTML =
            '<span class="d-fff__item-key">' + key + '</span>' +
            '<span>' + esc(match[2]) + '</span>' +
            (position !== -1 ? '<span class="d-fff__item-pos">' + (position + 1) + '</span>' : '');
          itemBox.appendChild(node);
        });
      }
    }

    setText('dFffAnswered', fff.answered);
    setText('dFffTotal', (fff.contenders || []).length);

    var board = el('dFffBoard');
    if (board) {
      board.innerHTML = '';
      (fff.contenders || []).forEach(function (c) {
        var node = document.createElement('div');
        var classes = 'd-fff__player';
        if (c.answered) classes += ' is-answered';
        if (c.is_correct === true) classes += ' is-correct';
        if (c.is_correct === false) classes += ' is-wrong';
        node.className = classes;
        node.innerHTML =
          (c.rank ? '<span class="d-fff__player-rank">' + c.rank + '</span>' : '') +
          '<span>' + esc(c.name) + '</span>' +
          (c.answered ? '<span class="d-fff__player-time">' + esc(c.time_label) + '</span>' : '');
        board.appendChild(node);
      });
    }

    var winnerBox = el('dFffWinner');
    if (winnerBox) {
      if (fff.status === 'closed' && fff.winner) {
        winnerBox.innerHTML =
          '<div class="d-fff__winner-label">હૉટ સીટ પર જાય છે</div>' +
          '<div class="d-fff__winner-name">' + esc(fff.winner.name) + '</div>' +
          '<div class="d-fff__winner-time">' + esc(fff.winner.time_label) + '</div>';
        winnerBox.hidden = false;
      } else if (fff.status === 'closed') {
        winnerBox.innerHTML = '<div class="d-fff__winner-label">કોઈએ સાચો ક્રમ આપ્યો નહીં</div>';
        winnerBox.hidden = false;
      } else {
        winnerBox.hidden = true;
      }
    }

    fffRemaining = fff.remaining_ms;
    fffSyncedAt = Date.now();
    return true;
  }

  function paintFffClock() {
    var overlay = el('dFffOverlay');
    if (!overlay || overlay.hidden || !state.fff) return;

    var left = state.fff.status === 'running'
      ? Math.max(0, fffRemaining - (Date.now() - fffSyncedAt))
      : fffRemaining;

    var clock = el('dFffClock');
    if (clock) {
      clock.textContent = state.fff.status === 'pending' ? 'તૈયાર' : Math.ceil(left / 1000);
      clock.className = 'd-fff__clock' + (left <= 5000 && state.fff.status === 'running' ? ' is-danger' : '');
    }
    var fill = el('dFffFill');
    if (fill && state.fff.total_ms > 0) {
      fill.style.width = ((left / state.fff.total_ms) * 100) + '%';
    }
  }

  /* --- Overlays ------------------------------------------------------------ */
  function hideOverlays() {
    ['dResultOverlay', 'dPollOverlay', 'dExpertOverlay', 'dFinalOverlay', 'dChequeOverlay', 'dFffOverlay'].forEach(function (id) {
      var node = el(id);
      if (node) node.hidden = true;
    });
  }

  function renderOverlay() {
    // A Fastest Finger round owns the whole screen while it runs.
    if (state.fff && state.fff.status !== 'pending') {
      hideOverlays();
      renderFff();
      return;
    }
    var fffOverlay = el('dFffOverlay');
    if (fffOverlay) { fffOverlay.hidden = true; }

    var s = state.state;
    var finished = state.is_finished;

    if (finished && (s === 'GAME_COMPLETED' || state.status === 'quit' || state.status === 'abandoned')) {
      showFinal();
      return;
    }
    var kind = s === 'CORRECT' ? 'correct' : (s === 'WRONG' ? 'wrong' : (s === 'TIME_UP' ? 'timeup' : ''));
    if (kind !== '') { queueResult(kind); return; }

    cancelResult();
    hideOverlays();
  }

  /**
   * Holds the result panel back for a moment so the audience first sees the
   * board itself answer the question: the chosen option turning red and the
   * right one turning green. Then the panel spells it out.
   */
  var resultTimer = null;
  var resultShownFor = '';

  function cancelResult() {
    if (resultTimer) { window.clearTimeout(resultTimer); resultTimer = null; }
    resultShownFor = '';
  }

  function queueResult(kind) {
    var key = kind + ':' + state.level + ':' + (state.answer.selected || '-');
    if (resultShownFor === key) { return; }   // already handled this reveal
    resultShownFor = key;

    if (resultTimer) { window.clearTimeout(resultTimer); }
    var overlay = el('dResultOverlay');
    if (overlay && !overlay.hidden) { showResult(kind); return; }

    // Long enough to read the board, short enough to keep the show moving.
    var wait = kind === 'correct' ? 1600 : 2400;
    resultTimer = window.setTimeout(function () {
      resultTimer = null;
      if (state.state === 'CORRECT' || state.state === 'WRONG' || state.state === 'TIME_UP') {
        showResult(kind);
      }
    }, wait);
  }

  function showResult(kind) {
    hideOverlays();
    var overlay = el('dResultOverlay');
    if (!overlay) return;

    var icon = kind === 'correct' ? '✓' : (kind === 'wrong' ? '✕' : '⏱');
    var title = kind === 'correct' ? 'સાચો જવાબ' : (kind === 'wrong' ? 'ખોટો જવાબ' : 'સમય પૂરો');
    var subtitle = kind === 'correct' ? 'CORRECT ANSWER' : (kind === 'wrong' ? 'WRONG ANSWER' : 'TIME UP');

    overlay.className = 'd-overlay d-overlay--' + kind;
    setText('dResultIcon', icon);
    setText('dResultTitle', title);
    setText('dResultSub', subtitle);

    // Spell out what was answered and what the answer actually was.
    var options = (state.question && state.question.options) || {};
    var given = state.answer.selected;
    var right = state.answer.correct;
    var rows = el('dResultAnswers');
    var givenRow = el('dResultGiven');
    var rightRow = el('dResultRight');

    if (givenRow) {
      givenRow.hidden = !given;
      if (given) {
        setText('dResultGivenKey', given);
        setText('dResultGivenText', options[given] || '');
        givenRow.classList.toggle('is-right', !!right && given === right);
        setText('dResultGivenLabel', kind === 'timeup'
          ? 'સમય પૂરો થયો · No answer locked'
          : 'તમારો જવાબ · Your answer');
      }
    }
    if (rightRow) {
      // Only ever shown once the operator has revealed the result.
      var showRight = !!right && (kind !== 'correct' || !given);
      rightRow.hidden = !showRight;
      if (showRight) {
        setText('dResultRightKey', right);
        setText('dResultRightText', options[right] || '');
      }
    }
    if (rows) {
      rows.hidden = (!givenRow || givenRow.hidden) && (!rightRow || rightRow.hidden);
    }

    var explanation = el('dResultExplanation');
    if (explanation) {
      var text = (state.question && state.question.explanation) || '';
      explanation.hidden = text === '';
      explanation.textContent = text;
    }

    var amount = el('dResultAmount');
    if (amount) {
      amount.textContent = kind === 'correct'
        ? (state.prize ? state.prize.won_so_far_label : '')
        : (state.prize ? state.prize.guaranteed_label : '');
    }
    var amountLabel = el('dResultAmountLabel');
    if (amountLabel) {
      amountLabel.textContent = kind === 'correct' ? 'Total winnings' : 'Takes home';
    }

    var gift = el('dResultGift');
    if (gift) {
      var giftName = kind === 'correct' && state.prize ? state.prize.gift_name : null;
      gift.textContent = giftName ? '🎁  ' + giftName : '';
      gift.style.display = giftName ? '' : 'none';
    }

    overlay.hidden = false;
    forceFit();
    if (kind === 'correct') { confetti(60); }
  }

  /** Number to words, so the cheque reads like a real one. */
  function amountInWords(amount) {
    amount = Math.floor(Math.abs(amount));
    if (amount === 0) return 'Zero rupees only';

    var ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
      'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    var tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    function under100(n) {
      if (n < 20) return ones[n];
      return tens[Math.floor(n / 10)] + (n % 10 ? '-' + ones[n % 10] : '');
    }
    function under1000(n) {
      if (n < 100) return under100(n);
      return ones[Math.floor(n / 100)] + ' hundred' + (n % 100 ? ' ' + under100(n % 100) : '');
    }

    // Indian grouping: crore, lakh, thousand, hundred.
    var parts = [];
    var crore = Math.floor(amount / 10000000); amount %= 10000000;
    var lakh = Math.floor(amount / 100000); amount %= 100000;
    var thousand = Math.floor(amount / 1000); amount %= 1000;

    if (crore) parts.push(under1000(crore) + ' crore');
    if (lakh) parts.push(under1000(lakh) + ' lakh');
    if (thousand) parts.push(under1000(thousand) + ' thousand');
    if (amount) parts.push(under1000(amount));

    var words = parts.join(' ');
    return words.charAt(0).toUpperCase() + words.slice(1) + ' rupees only';
  }

  function showCheque() {
    hideOverlays();
    var overlay = el('dChequeOverlay');
    if (!overlay) return false;

    var won = state.prize ? parseFloat(state.prize.final_prize) : 0;
    if (won <= 0) return false;

    setText('dChequeOrg', settings.site_name || '');
    setText('dChequeDate', new Date().toLocaleDateString());
    setText('dChequePayee', (state.participant && state.participant.name) || '');
    setText('dChequeAmount', state.prize.final_prize_label);
    setText('dChequeWords', amountInWords(won));

    overlay.hidden = false;
    confetti(140);

    // Hold the cheque, then hand over to the congratulations screen.
    window.setTimeout(function () {
      var node = el('dChequeOverlay');
      if (node && !node.hidden) { node.hidden = true; showFinal(true); }
    }, 8000);

    return true;
  }

  function showFinal(skipCheque) {
    var won = state.prize ? parseFloat(state.prize.final_prize) : 0;

    // The cheque comes first on a win, if it is switched on.
    if (!skipCheque && won > 0 && settings.cheque_animation !== false && !chequeShownFor) {
      chequeShownFor = state.game_id;
      if (showCheque()) return;
    }

    hideOverlays();
    var overlay = el('dFinalOverlay');
    if (!overlay) return;
    setText('dFinalIcon', won > 0 ? '🏆' : '🙏');
    setText('dFinalTitle', won > 0 ? 'અભિનંદન!' : 'આભાર!');
    setText('dFinalName', (state.participant && state.participant.name) || '');
    setText('dFinalAmount', state.prize ? state.prize.final_prize_label : '');

    var gifts = el('dFinalGifts');
    if (gifts) {
      var names = (state.gifts_won || []).map(function (g) { return g.name; });
      gifts.textContent = names.length ? '🎁  ' + names.join('   ·   ') : '';
      gifts.style.display = names.length ? '' : 'none';
    }

    overlay.hidden = false;
    if (won > 0) confetti(120);
  }

  function showLifelineOverlay(lifeline) {
    if (lifeline.code === 'audience_poll' && lifeline.result && lifeline.result.percentages) {
      hideOverlays();
      var box = el('dPollBars');
      if (box) {
        box.innerHTML = '';
        ['A', 'B', 'C', 'D'].forEach(function (key) {
          var pct = lifeline.result.percentages[key] || 0;
          var col = document.createElement('div');
          col.className = 'd-poll__col';
          col.innerHTML = '<div class="d-poll__pct">' + pct + '%</div>' +
            '<div class="d-poll__bar" style="height:0"></div>' +
            '<div class="d-poll__key">' + key + '</div>';
          box.appendChild(col);
          window.setTimeout(function () {
            col.querySelector('.d-poll__bar').style.height = Math.max(2, pct) + '%';
          }, 60);
        });
      }
      el('dPollOverlay').hidden = false;
      window.setTimeout(function () {
        var node = el('dPollOverlay');
        if (node) node.hidden = true;
      }, 9000);
      return;
    }

    if (lifeline.code === 'expert_advice' && lifeline.result) {
      hideOverlays();
      setText('dExpertName', lifeline.result.expert_name || 'Expert');
      setText('dExpertAnswer', lifeline.result.suggested_option || '?');
      setText('dExpertConfidence', (lifeline.result.confidence || 0) + '% confident');
      setText('dExpertMessage', lifeline.result.message || '');
      var photo = el('dExpertPhoto');
      if (photo) {
        photo.innerHTML = lifeline.result.expert_photo
          ? '<img src="' + esc(lifeline.result.expert_photo) + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:3vmin">'
          : '👤';
      }
      el('dExpertOverlay').hidden = false;
      window.setTimeout(function () {
        var node = el('dExpertOverlay');
        if (node) node.hidden = true;
      }, 9000);
    }
  }

  /* --- Confetti ------------------------------------------------------------ */
  function confetti(count) {
    if (!config.animations) return;
    var box = el('dConfetti');
    if (!box) return;
    var colours = ['#f5a623', '#ffd76e', '#b3141a', '#22c55e', '#ffffff', '#ff8a3d'];
    for (var i = 0; i < count; i++) {
      var piece = document.createElement('span');
      piece.style.left = Math.random() * 100 + '%';
      piece.style.background = colours[Math.floor(Math.random() * colours.length)];
      piece.style.animationDuration = (2.2 + Math.random() * 2.4) + 's';
      piece.style.animationDelay = (Math.random() * 0.9) + 's';
      box.appendChild(piece);
      (function (node) {
        window.setTimeout(function () { if (node.parentNode) node.parentNode.removeChild(node); }, 6000);
      })(piece);
    }
  }

  /* --- Transition detection ------------------------------------------------ */
  function reactToChange(previousState, previousLevel) {
    var s = state.state;

    if (state.level !== previousLevel && state.question) { play('question_start'); }
    if (s === 'TIMER_RUNNING' && previousState !== 'TIMER_RUNNING') { play('timer_start'); }
    if (s === 'ANSWER_LOCKED' && previousState !== 'ANSWER_LOCKED') { play('answer_lock'); }
    if (s === 'CORRECT' && previousState !== 'CORRECT') {
      play('correct_answer');
      if (state.prize && state.prize.gift_name) { play('prize_won'); }
    }
    if (s === 'WRONG' && previousState !== 'WRONG') { play('wrong_answer'); }
    if (s === 'TIME_UP' && previousState !== 'TIME_UP') { play('game_over'); }
    if (s === 'GAME_COMPLETED' && previousState !== 'GAME_COMPLETED') {
      play(state.prize && parseFloat(state.prize.final_prize) > 0 ? 'final_win' : 'game_over');
    }

    // A newly used lifeline gets its own animation.
    var signature = (state.lifelines || []).map(function (l) {
      return l.code + ':' + l.used + ':' + (l.result ? '1' : '0');
    }).join('|');

    if (signature !== lastLifelineSignature && lastLifelineSignature !== '') {
      (state.lifelines || []).forEach(function (lifeline) {
        if (lifeline.result && lastLifelineSignature.indexOf(lifeline.code + ':' + lifeline.used + ':1') === -1) {
          play('lifeline_used');
          showLifelineOverlay(lifeline);
        }
      });
    }
    lastLifelineSignature = signature;
  }

  /* --- Polling ------------------------------------------------------------- */
  function poll() {
    if (polling) return;
    polling = true;

    fetch(config.endpoint + '?v=' + lastVersion, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    }).then(function (response) { return response.json(); })
      .then(function (result) {
        polling = false;
        failures = 0;
        el('dOffline').hidden = true;

        if (!result || !result.success) return;

        var previousState = state.state;
        var previousLevel = state.level;

        state = result.data;
        settings = state.settings || settings;

        var changed = state.state_version !== lastVersion;
        lastVersion = state.state_version;

        render();
        if (changed) { reactToChange(previousState, previousLevel); }
      })
      .catch(function () {
        polling = false;
        failures++;
        if (failures >= 3) { el('dOffline').hidden = false; }
      })
      .finally(function () {
        window.setTimeout(poll, config.pollInterval);
      });
  }

  /* --- Full screen --------------------------------------------------------- */
  var fsButton = el('dFullscreen');
  if (fsButton) {
    fsButton.addEventListener('click', function () {
      if (!document.fullscreenElement) {
        (document.documentElement.requestFullscreen ||
         document.documentElement.webkitRequestFullscreen ||
         function () {}).call(document.documentElement);
      } else {
        (document.exitFullscreen || document.webkitExitFullscreen || function () {}).call(document);
      }
    });
  }
  document.addEventListener('fullscreenchange', function () {
    if (fsButton) fsButton.textContent = document.fullscreenElement ? 'Exit full screen' : 'Full screen';
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'f' || event.key === 'F') { if (fsButton) fsButton.click(); }
  });

  var reloadButton = el('dReload');
  if (reloadButton) reloadButton.addEventListener('click', function () { window.location.reload(); });

  /* --- Start --------------------------------------------------------------- */
  lastVersion = state.state_version;
  lastLifelineSignature = (state.lifelines || []).map(function (l) {
    return l.code + ':' + l.used + ':' + (l.result ? '1' : '0');
  }).join('|');

  render();
  scheduleFit();
  window.setInterval(paintTimer, 100);
  window.setInterval(function () {
    if (reloadPending && !timerRunning) { window.location.reload(); }
  }, 3000);
  window.setInterval(paintVoteCountdown, 500);
  window.setInterval(paintFffClock, 150);
  window.setTimeout(poll, 400);
})();
