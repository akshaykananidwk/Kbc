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
        ? '<img src="' + esc(participant.photo) + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:1.4vmin">'
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

  /* --- Overlays ------------------------------------------------------------ */
  function hideOverlays() {
    ['dResultOverlay', 'dPollOverlay', 'dExpertOverlay', 'dFinalOverlay', 'dChequeOverlay'].forEach(function (id) {
      var node = el(id);
      if (node) node.hidden = true;
    });
  }

  function renderOverlay() {
    var s = state.state;
    var finished = state.is_finished;

    if (finished && (s === 'GAME_COMPLETED' || state.status === 'quit' || state.status === 'abandoned')) {
      showFinal();
      return;
    }
    if (s === 'CORRECT') { showResult('correct'); return; }
    if (s === 'WRONG') { showResult('wrong'); return; }
    if (s === 'TIME_UP') { showResult('timeup'); return; }
    hideOverlays();
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

    var detail = el('dResultDetail');
    if (detail) {
      var parts = [];
      if (state.answer.selected) parts.push('Answered: ' + state.answer.selected);
      if (state.answer.correct) parts.push('Correct: ' + state.answer.correct);
      detail.textContent = parts.join('  ·  ');
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
  window.setInterval(paintTimer, 100);
  window.setTimeout(poll, 400);
})();
