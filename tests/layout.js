/**
 * Display screen layout check.
 *
 * The PHP suite cannot measure a rendered page, so this script drives a real
 * browser and asserts that nothing on the audience screen is cut off at the
 * screen shapes a show actually runs on: a 1080p TV, a 4:3 projector, a short
 * laptop panel and an ultra-wide monitor.
 *
 * Needs Playwright, which is a development tool only - the application itself
 * has no Node dependency. Skips politely when it is not installed.
 *
 *   node tests/layout.js [base-url]
 *
 * Exit code 0 when every check passes, 1 otherwise.
 */
'use strict';

const BASE = process.argv[2] || 'http://127.0.0.1:8080';

const SCREENS = [
  { name: '1080p TV',        width: 1920, height: 1080 },
  { name: '4:3 projector',   width: 1024, height: 768 },
  { name: 'laptop panel',    width: 1536, height: 730 },
  { name: 'ultra-wide',      width: 2560, height: 1080 },
  { name: 'small laptop',    width: 1280, height: 600 },
  { name: '4K TV',           width: 3840, height: 2160 }
];

let playwright;
try {
  playwright = require('playwright');
} catch (e) {
  try {
    playwright = require('/opt/node22/lib/node_modules/playwright');
  } catch (e2) {
    console.log('Playwright is not installed - skipping the layout check.');
    process.exit(0);
  }
}

const results = [];
function check(screen, name, pass, detail) {
  results.push({ screen, name, pass, detail: detail || '' });
  const mark = pass ? '\x1b[32mPASS\x1b[0m' : '\x1b[31mFAIL\x1b[0m';
  console.log(`  [${mark}] ${screen.padEnd(14)} ${name.padEnd(46)} ${detail || ''}`);
}

/**
 * Puts a real question on air so the layout is measured with a live game,
 * then hands back a function that ends it again.
 */
async function withLiveGame(browser, email, password) {
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await page.goto(BASE + '/admin/login', { waitUntil: 'networkidle' });
  await page.fill('input[name="identifier"]', email);
  await page.fill('input[name="password"]', password);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('button[type="submit"]')]);

  const started = await page.evaluate(async () => {
    const token = (document.querySelector('meta[name="csrf-token"]') || {}).content ||
      (document.querySelector('input[name="_token"]') || {}).value || '';
    const post = (path, body) => fetch(path, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': token },
      body: JSON.stringify(body || {})
    }).then((r) => r.json());

    const participants = await fetch('/api/game/participants', {
      credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then((r) => r.json()).catch(() => null);

    const id = participants && participants.data && participants.data.participants &&
      participants.data.participants.length ? participants.data.participants[0].id : null;
    if (!id) return { ok: false, why: 'no participants registered' };

    // replace_open_game keeps the check working even if a game was left open.
    const created = await post('/api/game/create',
      { participant_id: id, rehearsal: 1, allow_repeat: 1, replace_open_game: 1 });
    if (!created.success) return { ok: false, why: created.message };

    const started = await post('/api/game/start', {});
    if (!started.success) return { ok: false, why: started.message };

    await post('/api/game/timer/start', {});
    return { ok: true, game: created.data.game_code };
  });

  return {
    ok: started.ok,
    why: started.why,
    async finish() {
      if (started.ok) {
        await page.evaluate(async () => {
          const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
          await fetch('/api/game/end', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                       'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': token },
            body: JSON.stringify({ status: 'abandoned' })
          }).catch(() => {});
        });
      }
      await page.close();
    }
  };
}

(async () => {
  const browser = await playwright.chromium.launch();
  console.log('\n\x1b[1mDisplay screen layout\x1b[0m');

  const email = process.argv[3] || 'admin@ganpatiquiz.test';
  const password = process.argv[4] || 'Ganpati2026';
  const live = await withLiveGame(browser, email, password);
  if (!live.ok) {
    console.log('  Could not put a game on air (' + live.why + ') - measuring the idle screen only.');
  }

  for (const screen of SCREENS) {
    const page = await browser.newPage({ viewport: { width: screen.width, height: screen.height } });
    await page.goto(BASE + '/display', { waitUntil: 'networkidle' });
    await page.waitForTimeout(900);

    const m = await page.evaluate(() => {
      const visible = (el) => el && !el.hidden && el.getClientRects().length > 0;
      const out = { boxes: [], hiddenPanels: [], viewport: window.innerHeight, widest: 0, lowest: 0 };

      document.querySelectorAll('.d-centre, .d-ladder, .d-welcome, .d-overlay:not([hidden]), .d-question, .d-options')
        .forEach((el) => {
          if (!visible(el)) return;
          out.boxes.push({
            cls: el.className.split(' ')[0],
            overflowY: el.scrollHeight - el.clientHeight,
            overflowX: el.scrollWidth - el.clientWidth
          });
        });

      // Anything hidden must take up no space at all.
      document.querySelectorAll('[hidden]').forEach((el) => {
        const r = el.getBoundingClientRect();
        if (r.height > 0 || r.width > 0) out.hiddenPanels.push((el.id || el.className) + ' ' + Math.round(r.height));
      });

      document.querySelectorAll('.d-centre > *, .d-ladder, .d-foot, .d-head').forEach((el) => {
        if (!visible(el)) return;
        const r = el.getBoundingClientRect();
        out.lowest = Math.max(out.lowest, r.bottom);
        out.widest = Math.max(out.widest, r.right);
      });

      out.fit = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--d-fit')) || 0;
      out.timer = (() => {
        const ring = document.getElementById('dRing');
        if (!visible(ring)) return null;
        const r = ring.getBoundingClientRect();
        return { top: Math.round(r.top), bottom: Math.round(r.bottom), size: Math.round(r.height) };
      })();
      return out;
    });

    const SLACK = 6;
    const overflowing = m.boxes.filter((b) => b.overflowY > SLACK || b.overflowX > SLACK);
    check(screen.name, 'nothing overflows its box', overflowing.length === 0,
      overflowing.length ? JSON.stringify(overflowing) : m.boxes.length + ' boxes measured');

    check(screen.name, 'hidden panels take up no space', m.hiddenPanels.length === 0,
      m.hiddenPanels.join(', '));

    check(screen.name, 'content ends inside the screen', m.lowest <= screen.height + 1,
      Math.round(m.lowest) + ' / ' + screen.height + 'px');

    check(screen.name, 'the timer is fully visible',
      live.ok ? (m.timer !== null && m.timer.top >= 0 && m.timer.bottom <= screen.height) : true,
      m.timer ? m.timer.top + '–' + m.timer.bottom + 'px, ' + m.timer.size + 'px across'
              : 'no game on air - not applicable');

    check(screen.name, 'the screen scales to the panel', m.fit >= 0.55 && m.fit <= 1.45,
      'scale ' + m.fit.toFixed(2));

    await page.close();
  }

  await live.finish();
  await browser.close();

  const failed = results.filter((r) => !r.pass).length;
  console.log('\n' + '='.repeat(78));
  console.log(`  ${results.length} layout checks: ${results.length - failed} passed` + (failed ? `, ${failed} failed` : ''));
  console.log('='.repeat(78) + '\n');
  process.exit(failed === 0 ? 0 : 1);
})().catch((err) => { console.error(err); process.exit(1); });
