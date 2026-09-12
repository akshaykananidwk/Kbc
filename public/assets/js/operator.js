/**
 * Operator console.
 *
 * Every action is a server call - the browser never decides anything about
 * the game. After each call the returned state is re-rendered, and a poll
 * keeps the screen in step if a second operator window is open.
 */
(function () {
  'use strict';

  var root = document.getElementById('opRoot');
  if (!root) return;

  var config = JSON.parse(root.getAttribute('data-config'));
  var state = JSON.parse(document.getElementById('opState').textContent);
  var busy = false;
  var answerVisible = true;
  var localTimer = null;
  var pollTimer = null;

  var api = function (path) { return config.base.replace(/\/$/, '') + path; };

  function esc(value) {
    var div = document.createElement('div');
    div.textContent = value === null || value === undefined ? '' : String(value);
    return div.innerHTML;
  }

  function setStatus(message, kind) {
    var box = document.getElementById('opStatus');
    if (!box) return;
    box.className = 'op-status' + (kind ? ' is-' + kind : '');
    box.textContent = message;
  }

  function post(path, payload) {
    if (busy) return Promise.resolve(null);
    busy = true;
    document.body.style.cursor = 'progress';

    return fetch(api(path), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': config.token
      },
      body: JSON.stringify(payload || {})
    }).then(function (response) {
      return response.json().catch(function () {
        return { success: false, message: 'The server returned an unreadable response.' };
      });
    }).then(function (result) {
      busy = false;
      document.body.style.cursor = '';
      if (!result) return null;
      if (result.success) {
        state = result.data;
        render();
        setStatus(result.message, 'ok');
      } else {
        setStatus(result.message || 'That action could not be completed.', 'bad');
        refresh();
      }
      return result;
    }).catch(function () {
      busy = false;
      document.body.style.cursor = '';
      setStatus('Could not reach the server. Check the connection and try again.', 'bad');
      return null;
    });
  }

  function refresh() {
    return fetch(api('/api/game/state'), {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json(); })
      .then(function (result) {
        if (result && result.success) { state = result.data; render(); }
      }).catch(function () { /* transient - the poll will retry */ });
  }

  /* --- Timer ------------------------------------------------------------- */
  var timerRemaining = 0;
  var timerSyncedAt = 0;

  function syncTimer() {
    timerRemaining = state.timer ? state.timer.remaining_ms : 0;
    timerSyncedAt = Date.now();
  }

  function currentRemaining() {
    if (!state.timer || !state.timer.running) return timerRemaining;
    return Math.max(0, timerRemaining - (Date.now() - timerSyncedAt));
  }

  function paintTimer() {
    var valueEl = document.getElementById('opTimerValue');
    var fillEl = document.getElementById('opTimerFill');
    var stateEl = document.getElementById('opTimerState');
    if (!valueEl) return;

    var remaining = currentRemaining();
    var total = state.timer ? state.timer.total_ms : 0;
    var seconds = Math.ceil(remaining / 1000);

    valueEl.textContent = seconds < 0 ? '0' : String(seconds);
    valueEl.className = 'op-timer__value' +
      (remaining <= 5000 && total > 0 ? ' is-danger' : (remaining <= 10000 && total > 0 ? ' is-warn' : ''));

    if (fillEl) fillEl.style.width = total > 0 ? Math.max(0, (remaining / total) * 100) + '%' : '0%';

    if (stateEl) {
      stateEl.textContent = !state.timer ? 'no timer'
        : state.timer.running ? 'running'
        : (remaining <= 0 && total > 0 ? 'TIME UP' : 'paused');
    }

    // When the local clock hits zero, ask the server to confirm the state.
    if (state.timer && state.timer.running && remaining <= 0) {
      state.timer.running = false;
      refresh();
    }
  }

  /* --- Rendering --------------------------------------------------------- */
  function render() {
    syncTimer();

    var hasGame = state.has_game;
    var question = state.question;
    var privateData = state.private || {};
    var finished = state.is_finished;

    // Header chips
    setText('opGameCode', hasGame ? state.game_code : '—');
    setText('opState', state.state.replace(/_/g, ' '));
    var statusChip = document.getElementById('opStatusChip');
    if (statusChip) {
      statusChip.textContent = hasGame ? state.status : 'no game';
      statusChip.className = 'op-bar__chip ' + (
        !hasGame ? 'op-bar__chip--idle'
          : finished ? 'op-bar__chip--bad'
          : state.status === 'running' ? 'op-bar__chip--live' : 'op-bar__chip--wait'
      );
    }

    // Participant
    var participant = state.participant || {};
    setText('opParticipantName', participant.name || 'No participant');
    setText('opParticipantMeta', [participant.reg_no, participant.city].filter(Boolean).join(' · ') || '—');
    var photo = document.getElementById('opParticipantPhoto');
    if (photo) {
      if (participant.photo) {
        photo.innerHTML = '<img src="' + esc(participant.photo) + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:12px">';
      } else {
        photo.textContent = (participant.name || '?').charAt(0);
      }
    }

    // Meta strip
    setText('opLevel', hasGame && state.level > 0 ? state.level + ' / ' + state.total_levels : '—');
    setText('opPrize', state.prize ? state.prize.current_label : '—');
    setText('opNextPrize', state.prize && state.prize.next_label ? state.prize.next_label : 'final question');
    setText('opWon', state.prize ? state.prize.won_so_far_label : '—');
    setText('opGuaranteed', state.prize ? state.prize.guaranteed_label : '—');
    setText('opGift', state.prize && state.prize.gift_name ? state.prize.gift_name : 'none');

    // Question
    var questionBox = document.getElementById('opQuestion');
    if (questionBox) {
      questionBox.textContent = question ? question.text : (hasGame ? 'No question is on air yet.' : 'No game in progress.');
    }

    renderOptions(question, privateData);
    renderAnswerKey(question, privateData);
    renderLifelines();
    renderLadder();
    renderControls();
    paintTimer();
  }

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value === null || value === undefined ? '—' : String(value);
  }

  function renderOptions(question, privateData) {
    var box = document.getElementById('opOptions');
    if (!box) return;
    box.innerHTML = '';
    if (!question) return;

    var revealed = state.answer.revealed;
    var correct = privateData.correct_option;
    var removed = question.removed || [];
    var allOptions = privateData.all_options || question.options;

    ['A', 'B', 'C', 'D'].forEach(function (key) {
      var text = allOptions[key] || '';
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'op-option';
      button.setAttribute('data-option', key);

      var isRemoved = removed.indexOf(key) !== -1;
      var isSelected = state.answer.selected === key;

      if (isRemoved) button.classList.add('is-removed');
      if (isSelected) button.classList.add('is-selected');
      if (revealed && key === correct) { button.classList.add('is-correct'); }
      if (revealed && isSelected && key !== correct) { button.classList.add('is-wrong'); }

      var flags = [];
      if (isSelected) flags.push(state.answer.locked ? 'LOCKED' : 'selected');
      if (revealed && key === correct) flags.push('CORRECT');
      if (isRemoved) flags.push('50:50');

      button.innerHTML =
        '<span class="op-option__key">' + key + '</span>' +
        '<span>' + esc(text) + '</span>' +
        (flags.length ? '<span class="op-option__flag">' + esc(flags.join(' · ')) + '</span>' : '');

      button.disabled = state.answer.locked || revealed || state.is_finished || isRemoved;
      button.addEventListener('click', function () { post('/api/game/select', { option: key }); });
      box.appendChild(button);
    });
  }

  function renderAnswerKey(question, privateData) {
    var box = document.getElementById('opAnswerKey');
    if (!box) return;
    if (!question || !privateData.correct_option) {
      box.style.display = 'none';
      return;
    }
    box.style.display = '';
    box.classList.toggle('is-hidden', !answerVisible);
    setText('opAnswerKeyValue', privateData.correct_option);
    setText('opAnswerKeyText', (privateData.all_options || {})[privateData.correct_option] || '');
  }

  function renderLifelines() {
    var box = document.getElementById('opLifelines');
    if (!box) return;
    box.innerHTML = '';

    (state.lifelines || []).forEach(function (lifeline) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'op-lifeline' + (lifeline.available ? '' : ' is-used');
      button.disabled = !lifeline.available || !state.has_game || state.answer.locked ||
                        state.answer.revealed || state.is_finished || !state.question;

      var resultText = '';
      if (lifeline.result) {
        if (lifeline.result.removed) {
          resultText = 'removed ' + lifeline.result.removed.join(', ');
        } else if (lifeline.result.percentages) {
          resultText = Object.keys(lifeline.result.percentages).map(function (key) {
            return key + ' ' + lifeline.result.percentages[key] + '%';
          }).join(' · ');
        } else if (lifeline.result.suggested_option) {
          resultText = 'says ' + lifeline.result.suggested_option + ' (' + lifeline.result.confidence + '%)';
        }
      }

      button.innerHTML = '<span>' + esc(lifeline.name) + '</span>' +
        '<span class="op-lifeline__result">' + esc(resultText || (lifeline.available ? lifeline.uses + '× left' : 'used')) + '</span>';

      button.addEventListener('click', function () {
        if (!window.confirm('Use the "' + lifeline.name + '" lifeline now?')) return;
        post('/api/lifelines/use', { code: lifeline.code });
      });
      box.appendChild(button);
    });
  }

  function renderLadder() {
    var box = document.getElementById('opLadder');
    if (!box) return;
    box.innerHTML = '';

    (state.ladder || []).slice().reverse().forEach(function (level) {
      var row = document.createElement('div');
      row.className = 'op-ladder__row' +
        (level.is_current ? ' is-current' : '') +
        (level.is_won && !level.is_current ? ' is-won' : '') +
        (level.guaranteed ? ' is-guaranteed' : '');
      row.innerHTML =
        '<span class="op-ladder__no">' + level.level + '</span>' +
        '<span>' + esc(level.label) + (level.gift_name ? ' <span class="op-ladder__gift">+ ' + esc(level.gift_name) + '</span>' : '') + '</span>';
      box.appendChild(row);
    });
  }

  function renderControls() {
    var hasGame = state.has_game;
    var hasQuestion = !!state.question;
    var finished = state.is_finished;
    var revealed = state.answer.revealed;
    var locked = state.answer.locked;
    var running = state.timer && state.timer.running;
    var expired = state.timer && state.timer.expired;
    var pending = state.status === 'pending';

    enable('btnStartGame', hasGame && pending);
    enable('btnStartTimer', hasGame && hasQuestion && !running && !locked && !revealed && !finished && !expired);
    enable('btnPauseTimer', hasGame && running);
    enable('btnResumeTimer', hasGame && hasQuestion && !running && !locked && !revealed && !finished && !expired && state.timer.remaining_ms > 0);
    enable('btnResetTimer', hasGame && hasQuestion && !revealed && !finished);
    enable('btnLock', hasGame && hasQuestion && !!state.answer.selected && !locked && !revealed && !finished);
    enable('btnUnlock', hasGame && locked && !revealed && !finished);
    enable('btnReveal', hasGame && hasQuestion && !revealed && !finished && (locked || expired || !config.requireLock));
    enable('btnNext', hasGame && revealed && !finished);
    enable('btnPrevious', hasGame && state.level > 1 && !finished);
    enable('btnRestart', hasGame && hasQuestion && !finished);
    enable('btnQuit', hasGame && !finished && config.allowQuit);
    enable('btnEnd', hasGame && !finished);
    enable('btnReset', hasGame);

    var summary = document.getElementById('btnSummary');
    if (summary) {
      summary.style.display = finished && hasGame ? '' : 'none';
      summary.href = config.base.replace(/\/$/, '') + '/operator/summary/' + state.game_id;
    }
  }

  function enable(id, condition) {
    var el = document.getElementById(id);
    if (el) el.disabled = !condition;
  }

  /* --- Wiring ------------------------------------------------------------ */
  function bind(id, handler) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('click', handler);
  }

  bind('btnStartGame', function () { post('/api/game/start', {}); });
  bind('btnStartTimer', function () { post('/api/game/timer/start', {}); });
  bind('btnPauseTimer', function () { post('/api/game/timer/pause', {}); });
  bind('btnResumeTimer', function () { post('/api/game/timer/resume', {}); });
  bind('btnResetTimer', function () { post('/api/game/timer/reset', {}); });
  bind('btnLock', function () {
    if (!window.confirm('Lock answer ' + state.answer.selected + '?\n\nAfter locking it can only be changed with an override, which is recorded in the audit log.')) return;
    post('/api/game/lock', {});
  });
  bind('btnUnlock', function () {
    var reason = window.prompt('Override the lock.\n\nWhy are you unlocking this answer? (recorded in the audit log)');
    if (reason === null) return;
    post('/api/game/unlock', { reason: reason });
  });
  bind('btnReveal', function () {
    if (!window.confirm('Reveal the result now?\n\nThe display screen will show whether the answer is right or wrong.')) return;
    post('/api/game/reveal', {});
  });
  bind('btnNext', function () { post('/api/game/next', {}); });
  bind('btnPrevious', function () {
    if (!window.confirm('Go back to the previous question?\n\nThat question\'s recorded answer will be cleared so it can be played again. This is recorded in the audit log.')) return;
    post('/api/game/previous', {});
  });
  bind('btnRestart', function () {
    if (!window.confirm('Restart this question?\n\nThe timer and the selected answer will be cleared.')) return;
    post('/api/game/restart', {});
  });
  bind('btnQuit', function () {
    if (!window.confirm('The participant quits now?\n\nThey keep ' + (state.prize ? state.prize.won_so_far_label : '') + ' and the game ends.')) return;
    post('/api/game/quit', {});
  });
  bind('btnEnd', function () {
    if (!window.confirm('End this game?\n\nThe game will be closed and recorded as abandoned.')) return;
    post('/api/game/end', { status: 'abandoned' });
  });
  bind('btnReset', function () {
    if (!window.confirm('RESET this game?\n\nAll answers, lifelines and questions for this game are cleared and it starts again from the beginning.\nThe audit log is kept.\n\nThis cannot be undone. Continue?')) return;
    post('/api/game/reset', { start_new: false });
  });
  bind('btnToggleAnswer', function () {
    answerVisible = !answerVisible;
    var button = document.getElementById('btnToggleAnswer');
    if (button) button.textContent = answerVisible ? 'Hide answer' : 'Show answer';
    renderAnswerKey(state.question, state.private || {});
  });

  /* Keyboard shortcuts for fast operation */
  document.addEventListener('keydown', function (event) {
    if (event.target.matches('input, textarea, select')) return;
    if (event.ctrlKey || event.metaKey || event.altKey) return;

    var key = event.key.toUpperCase();
    if (['A', 'B', 'C', 'D'].indexOf(key) !== -1) {
      var button = document.querySelector('.op-option[data-option="' + key + '"]');
      if (button && !button.disabled) { event.preventDefault(); button.click(); }
      return;
    }
    var map = { ' ': 'btnStartTimer', 'P': 'btnPauseTimer', 'L': 'btnLock', 'R': 'btnReveal', 'N': 'btnNext' };
    var id = map[event.key === ' ' ? ' ' : key];
    if (id) {
      var target = document.getElementById(id);
      if (target && !target.disabled) { event.preventDefault(); target.click(); }
    }
  });

  /* --- Loops -------------------------------------------------------------- */
  localTimer = window.setInterval(paintTimer, 100);
  pollTimer = window.setInterval(function () { if (!busy) refresh(); }, config.pollInterval);
  window.addEventListener('beforeunload', function () {
    window.clearInterval(localTimer);
    window.clearInterval(pollTimer);
  });

  render();
  setStatus('Ready.', '');
})();
