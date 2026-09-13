/**
 * Ganpati Bapa Quiz Show - admin panel helpers.
 * No build step, no framework, no external dependencies.
 */
(function () {
  'use strict';

  /* --- Mobile navigation ------------------------------------------------ */
  var toggle = document.getElementById('navToggle');
  if (toggle) {
    toggle.addEventListener('click', function () {
      document.body.classList.toggle('nav-open');
    });
    document.addEventListener('click', function (event) {
      if (!document.body.classList.contains('nav-open')) return;
      var sidebar = document.getElementById('sidebar');
      if (sidebar && !sidebar.contains(event.target) && event.target !== toggle) {
        document.body.classList.remove('nav-open');
      }
    });
  }

  /* --- Dismissable alerts ----------------------------------------------- */
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-dismiss]');
    if (button && button.parentElement) {
      button.parentElement.remove();
    }
  });

  /* --- Confirmation before destructive actions -------------------------- */
  document.addEventListener('submit', function (event) {
    var form = event.target;
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
      return false;
    }
    // Stop accidental double submits.
    var submit = form.querySelector('[type="submit"]');
    if (submit && !form.hasAttribute('data-no-lock')) {
      window.setTimeout(function () {
        submit.disabled = true;
        submit.textContent = submit.getAttribute('data-busy') || 'Working…';
      }, 0);
      window.setTimeout(function () {
        submit.disabled = false;
      }, 12000);
    }
  });

  document.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-confirm]');
    if (link && !window.confirm(link.getAttribute('data-confirm'))) {
      event.preventDefault();
    }
  });

  /* --- Auto-submitting filter forms ------------------------------------- */
  document.querySelectorAll('[data-auto-submit]').forEach(function (element) {
    element.addEventListener('change', function () {
      var form = element.closest('form');
      if (form) form.submit();
    });
  });

  /* --- Live image preview for file inputs ------------------------------- */
  document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      var target = document.querySelector(input.getAttribute('data-preview'));
      if (!target || !input.files || !input.files[0]) return;
      var file = input.files[0];
      if (!/^image\//.test(file.type)) return;
      var reader = new FileReader();
      reader.onload = function (e) {
        target.src = e.target.result;
        target.style.display = 'block';
      };
      reader.readAsDataURL(file);
    });
  });

  /* --- Copy-to-clipboard ------------------------------------------------- */
  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var text = button.getAttribute('data-copy');
      var done = function () {
        var original = button.textContent;
        button.textContent = 'Copied!';
        window.setTimeout(function () { button.textContent = original; }, 1600);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done);
      } else {
        var field = document.createElement('textarea');
        field.value = text;
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ }
        document.body.removeChild(field);
      }
    });
  });

  /* --- Collapsible sections --------------------------------------------- */
  document.querySelectorAll('[data-toggle-target]').forEach(function (button) {
    button.addEventListener('click', function () {
      var target = document.querySelector(button.getAttribute('data-toggle-target'));
      if (!target) return;
      var hidden = target.hasAttribute('hidden');
      if (hidden) { target.removeAttribute('hidden'); } else { target.setAttribute('hidden', ''); }
      button.setAttribute('aria-expanded', hidden ? 'true' : 'false');
    });
  });

  /* --- Shared JSON helper used by the admin AJAX screens ---------------- */
  window.QuizApi = {
    token: function () {
      var meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.getAttribute('content') : '';
    },
    post: function (endpoint, payload) {
      return fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': window.QuizApi.token()
        },
        body: JSON.stringify(payload || {})
      }).then(function (response) {
        return response.json().catch(function () {
          return { success: false, message: 'The server returned an unreadable response.' };
        });
      });
    },
    get: function (endpoint) {
      return fetch(endpoint, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (response) {
        return response.json().catch(function () {
          return { success: false, message: 'The server returned an unreadable response.' };
        });
      });
    }
  };
})();

/**
 * Inline media fields.
 *
 * Uploads a sound or image straight into its setting over AJAX, so the
 * admin never has to type a file path or use a separate form.
 */
(function () {
  'use strict';

  var fields = document.querySelectorAll('[data-media-field]');
  if (!fields.length) return;

  var base = (function () {
    var link = document.querySelector('link[rel="stylesheet"][href*="assets/css/admin.css"]');
    if (!link) return '';
    return link.getAttribute('href').replace(/\/public\/assets\/css\/admin\.css.*$/, '');
  })();

  function tokenFor(field) {
    var form = field.closest('form');
    var input = form ? form.querySelector('input[name="_token"]') : null;
    if (input) return input.value;
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function setStatus(field, message, state) {
    var status = field.querySelector('[data-media-status]');
    if (!status) return;
    status.textContent = message;
    status.className = 'media-field__status' + (state ? ' is-' + state : '');
  }

  function renderPreview(field, url, kind) {
    var preview = field.querySelector('[data-media-preview]');
    if (!preview) return;
    preview.innerHTML = '';

    if (!url) {
      var empty = document.createElement('span');
      empty.className = 'media-field__empty';
      empty.textContent = kind === 'audio' ? '♪ No file yet' : 'No image yet';
      preview.appendChild(empty);
      return;
    }
    if (kind === 'audio') {
      var audio = document.createElement('audio');
      audio.controls = true;
      audio.preload = 'none';
      audio.src = url;
      preview.appendChild(audio);
    } else {
      var img = document.createElement('img');
      img.src = url;
      img.alt = '';
      preview.appendChild(img);
    }
  }

  var tester = null;

  fields.forEach(function (field) {
    var key = field.getAttribute('data-key');
    var kind = field.getAttribute('data-kind') || 'image';
    var input = field.querySelector('[data-media-input]');
    var removeBtn = field.querySelector('[data-media-remove]');
    var hidden = field.querySelector('[data-media-value]');
    var pick = field.querySelector('.media-field__pick');

    if (input) {
      input.addEventListener('change', function () {
        if (!input.files || !input.files[0]) return;
        var file = input.files[0];

        // Checked here as well as on the server: a browser that starts a
        // 12 MB upload against a 2 MB server limit just loses the whole
        // request, token and all, and the admin sees nothing useful.
        var max = parseInt(field.getAttribute('data-max') || '0', 10);
        if (max > 0 && file.size > max) {
          input.value = '';
          setStatus(field,
            'This file is ' + (file.size / 1048576).toFixed(1) + ' MB. This server accepts up to ' +
            (field.getAttribute('data-max-label') || (max / 1048576).toFixed(0) + ' MB') +
            '. Use a smaller file, or raise upload_max_filesize and post_max_size on your hosting.',
            'error');
          return;
        }

        field.classList.add('is-uploading');
        setStatus(field, 'Uploading ' + file.name + '…', 'busy');

        var payload = new FormData();
        payload.append('_token', tokenFor(field));
        payload.append('key', key);
        payload.append('file', file);

        fetch(base + '/admin/settings/upload', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: payload
        }).then(function (response) {
          return response.json().catch(function () {
            return { success: false, message: 'The server returned an unreadable response.' };
          });
        }).then(function (result) {
          field.classList.remove('is-uploading');
          input.value = '';

          if (!result.success) {
            setStatus(field, result.message || 'Upload failed.', 'error');
            return;
          }
          renderPreview(field, result.data.url, result.data.kind);
          if (hidden) hidden.value = result.data.stored;
          if (removeBtn) removeBtn.hidden = false;
          if (pick) pick.childNodes[0].nodeValue = 'Replace ';
          setStatus(field, 'Saved: ' + result.data.filename + ' (' + result.data.size + ')', 'done');
        }).catch(function () {
          field.classList.remove('is-uploading');
          setStatus(field, 'Could not reach the server.', 'error');
        });
      });
    }

    // "Test sound" plays whatever this setting will actually play during a
    // show: the uploaded file when there is one, otherwise the built-in tone.
    var testBtn = field.querySelector('[data-media-test]');
    if (testBtn) {
      testBtn.addEventListener('click', function () {
        var url = hidden && hidden.value ? base + '/public/' + hidden.value.replace(/^\/+/, '') : '';
        if (url) {
          var player = field.querySelector('[data-media-preview] audio');
          if (player) {
            player.currentTime = 0;
            player.play().catch(function () { setStatus(field, 'Your browser blocked playback. Press play on the player above.', 'error'); });
          } else {
            new Audio(url).play().catch(function () {});
          }
          setStatus(field, 'Playing your file…', 'done');
          return;
        }

        var cue = testBtn.getAttribute('data-cue') || '';
        if (!cue || typeof window.QuizAudio !== 'function') {
          setStatus(field, 'Upload a track to hear this one.', 'busy');
          return;
        }
        if (!tester) {
          tester = new window.QuizAudio({ soundEnabled: true, synthFallback: true, soundVolume: 80 });
        }
        tester.unlockNow();
        tester.play(cue);
        setStatus(field, 'Playing the built-in tone (no file uploaded).', 'done');
      });
    }

    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        if (!window.confirm('Remove this file?')) return;
        setStatus(field, 'Removing…', 'busy');

        var payload = new FormData();
        payload.append('_token', tokenFor(field));
        payload.append('key', key);

        fetch(base + '/admin/settings/remove-file', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: payload
        }).then(function (response) { return response.json(); })
          .then(function (result) {
            if (!result.success) { setStatus(field, result.message || 'Could not remove.', 'error'); return; }
            renderPreview(field, '', kind);
            if (hidden) hidden.value = '';
            removeBtn.hidden = true;
            if (pick) pick.childNodes[0].nodeValue = (kind === 'audio' ? 'Upload music ' : 'Upload image ');
            setStatus(field, 'Removed.', 'done');
          }).catch(function () { setStatus(field, 'Could not reach the server.', 'error'); });
      });
    }
  });
})();
