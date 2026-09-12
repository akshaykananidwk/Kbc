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
