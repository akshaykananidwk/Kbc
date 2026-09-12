/**
 * Fastest Finger First — operator console.
 *
 * The server owns the timing and the judging; this screen only issues
 * commands and renders what comes back.
 */
(function () {
  'use strict';

  var box = document.getElementById('fffRunBox');
  if (!box) return;

  var base = (function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var path = window.location.pathname.replace(/\/operator\/fff\/?$/, '');
    return { path: path, token: meta ? meta.getAttribute('content') : '' };
  })();

  var round = null;
  var order = [];
  var pollTimer = null;
  var clockTimer = null;
  var syncedAt = 0;
  var remainingMs = 0;

  function esc(value) {
    var div = document.createElement('div');
    div.textContent = value === null || value === undefined ? '' : String(value);
    return div.innerHTML;
  }

  function api(path, payload) {
    return fetch(base.path + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': base.token
      },
      body: JSON.stringify(payload || {})
    }).then(function (r) {
      return r.json().catch(function () {
        return { success: false, message: 'The server returned an unreadable response.' };
      });
    });
  }

  function apiGet(path) {
    return fetch(base.path + path, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json(); });
  }

  /* --- Order picker -------------------------------------------------------- */
  function repaintOrder() {
    document.querySelectorAll('[data-order-key]').forEach(function (button) {
      var key = button.getAttribute('data-order-key');
      var index = order.indexOf(key);
      button.classList.toggle('btn--gold', index !== -1);
      button.textContent = index === -1 ? key : key + ' (' + (index + 1) + ')';
    });
    var preview = document.getElementById('orderPreview');
    if (preview) {
      preview.textContent = order.length ? order.join(' ') : '— — — —';
    }
  }

  document.querySelectorAll('[data-order-key]').forEach(function (button) {
    button.addEventListener('click', function () {
      var key = button.getAttribute('data-order-key');
      var index = order.indexOf(key);
      if (index === -1) { if (order.length < 4) order.push(key); } else { order.splice(index, 1); }
      repaintOrder();
    });
  });
  var clearBtn = document.getElementById('orderClear');
  if (clearBtn) clearBtn.addEventListener('click', function () { order = []; repaintOrder(); });

  /* --- Create -------------------------------------------------------------- */
  document.getElementById('fffCreate').addEventListener('click', function () {
    var question = document.getElementById('fffQuestion').value.trim();
    var options = {};
    ['A', 'B', 'C', 'D'].forEach(function (key) {
      options[key] = document.getElementById('fffItem' + key).value.trim();
    });

    var participants = [];
    document.querySelectorAll('.fff-contender:checked').forEach(function (input) {
      participants.push(parseInt(input.value, 10));
    });

    if (!question) { window.alert('Enter the question.'); return; }
    if (Object.keys(options).some(function (k) { return !options[k]; })) { window.alert('Fill in all four items.'); return; }
    if (order.length !== 4) { window.alert('Tap the four items in the correct order.'); return; }
    if (participants.length < 2) { window.alert('Choose at least two contenders.'); return; }

    api('/api/fff/create', {
      question: question,
      options: options,
      order: order.join(''),
      participants: participants,
      time_limit: parseInt(document.getElementById('fffTime').value, 10) || 25
    }).then(function (result) {
      if (!result.success) { window.alert(result.message); return; }
      round = result.data.round;
      render();
      startPolling();
    });
  });

  /* --- Controls ------------------------------------------------------------ */
  function bind(id, handler) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('click', handler);
  }

  bind('fffStart', function () {
    api('/api/fff/start', { round_id: round.id }).then(function (result) {
      if (!result.success) { window.alert(result.message); return; }
      round = result.data.round;
      render();
    });
  });

  bind('fffClose', function () {
    if (!window.confirm('Close the round and reveal the result?')) return;
    api('/api/fff/close', { round_id: round.id }).then(function (result) {
      if (!result.success) { window.alert(result.message); return; }
      round = result.data.round;
      render();
    });
  });

  bind('fffCancel', function () {
    if (!window.confirm('Cancel this round? Nobody wins the hot seat.')) return;
    api('/api/fff/cancel', { round_id: round.id }).then(function () {
      round = null;
      stopPolling();
      render();
    });
  });

  /* --- Rendering ------------------------------------------------------------ */
  function render() {
    var empty = document.getElementById('fffEmpty');
    var live = document.getElementById('fffLive');
    var status = document.getElementById('fffStatus');

    if (!round) {
      empty.hidden = false;
      live.hidden = true;
      status.textContent = 'no round';
      return;
    }

    empty.hidden = true;
    live.hidden = false;
    status.textContent = round.status;
    status.className = 'badge badge--' + (round.status === 'running' ? 'warn' : (round.status === 'closed' ? 'ok' : 'muted'));

    var url = window.location.origin + base.path + '/fff/' + round.id;
    document.getElementById('fffUrl').textContent = url;
    document.getElementById('fffCopyUrl').setAttribute('data-copy', url);
    document.getElementById('fffQr').src = base.path + '/qr?for=fff&round=' + round.id + '&scale=5';

    setText('fffAnswered', round.answered);
    setText('fffTotal', round.contenders.length);

    remainingMs = round.remaining_ms;
    syncedAt = Date.now();
    paintClock();

    var codes = (round.private && round.private.access_codes) || {};
    var tbody = document.getElementById('fffTable');
    tbody.innerHTML = '';

    round.contenders.forEach(function (c) {
      var tr = document.createElement('tr');
      var result = c.is_correct === null
        ? '<span class="badge badge--muted">hidden</span>'
        : (c.is_correct
            ? '<span class="badge badge--ok">correct' + (c.rank ? ' · #' + c.rank : '') + '</span>'
            : '<span class="badge badge--bad">wrong</span>');

      tr.innerHTML =
        '<td><strong>' + esc(c.name) + '</strong>' + (c.city ? '<div class="small muted">' + esc(c.city) + '</div>' : '') + '</td>' +
        '<td class="mono" style="font-size:1.1rem;letter-spacing:.2em">' + esc(codes[c.participant_id] || '—') + '</td>' +
        '<td>' + (c.answered ? '<span class="badge badge--ok">yes</span>' : '<span class="badge badge--muted">waiting</span>') + '</td>' +
        '<td class="num">' + esc(c.time_label) + '</td>' +
        '<td>' + result + '</td>';
      tbody.appendChild(tr);
    });

    document.getElementById('fffStart').disabled = round.status !== 'pending';
    document.getElementById('fffClose').disabled = round.status === 'closed';
    document.getElementById('fffCancel').disabled = round.status === 'closed';

    var resultBox = document.getElementById('fffResult');
    if (round.status === 'closed') {
      resultBox.innerHTML = round.winner
        ? '<div class="alert alert--success"><span>🏆</span><span><strong>' + esc(round.winner.name) +
          '</strong> takes the hot seat — ' + esc(round.winner.time_label) +
          '. Correct order was <strong>' + esc(round.correct_order) + '</strong>.' +
          ' <a href="' + base.path + '/operator/setup">Start their game →</a></span></div>'
        : '<div class="alert alert--warning"><span>!</span><span>Nobody gave the correct order (' +
          esc(round.correct_order) + '). Run another round.</span></div>';
      stopPolling();
    } else {
      resultBox.innerHTML = '';
    }
  }

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value === null || value === undefined ? '—' : String(value);
  }

  function paintClock() {
    if (!round) return;
    var left = round.status === 'running'
      ? Math.max(0, remainingMs - (Date.now() - syncedAt))
      : remainingMs;
    setText('fffClock', round.status === 'pending' ? 'ready' : Math.ceil(left / 1000) + 's');
  }

  /* --- Polling -------------------------------------------------------------- */
  function startPolling() {
    stopPolling();
    pollTimer = window.setInterval(function () {
      if (!round) return;
      apiGet('/api/fff/state?round_id=' + round.id).then(function (result) {
        if (result.success && result.data.round) {
          round = result.data.round;
          render();
        }
      }).catch(function () { /* transient */ });
    }, 1200);
    clockTimer = window.setInterval(paintClock, 200);
  }

  function stopPolling() {
    if (pollTimer) { window.clearInterval(pollTimer); pollTimer = null; }
    if (clockTimer) { window.clearInterval(clockTimer); clockTimer = null; }
  }

  /* --- Boot ----------------------------------------------------------------- */
  apiGet('/api/fff/state').then(function (result) {
    if (result.success && result.data.round) {
      round = result.data.round;
      render();
      if (round.status !== 'closed') startPolling();
    } else {
      render();
    }
  }).catch(render);

  repaintOrder();
})();
