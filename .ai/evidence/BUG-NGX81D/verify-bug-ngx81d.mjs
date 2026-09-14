// QC BUG-NGX81D (SLP-204) — product review form: no button lock, inline error above submit button
// Drives chrome-headless-shell via raw CDP (Node built-in WebSocket), harness pattern from BUG-8K1TBB.
// CAPTCHA_MODE=A (default): captcha disabled for product_review (local default config)
//   AC-001: empty submit -> inline field message ABOVE submit button, button NOT disabled,
//           no "ReCaptcha validation failed" banner, 0 graphql calls
//   AC-006: captcha disabled -> fill all fields -> resubmit -> graphql flow runs (no refresh needed)
// CAPTCHA_MODE=B: captcha v2 checkbox temporarily enabled (dummy site key, widget cannot resolve a token)
//   AC-001B: empty submit -> field message, button NOT disabled, 0 graphql
//   AC-002: fields filled, no token -> captcha inline message, button NOT disabled, 0 graphql, no banner
const MODE = process.env.CAPTCHA_MODE || 'A';
const BASE = 'http://slaunchpad.localhost';
const PDP = BASE + '/joust-duffle-bag.html';
const CDP_PORT = 9333;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const results = [];
const check = (id, ok, detail) => {
  results.push({ id, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'} ${id}: ${detail}`);
};

// --- launch browser -----------------------------------------------------
const { spawn } = await import('node:child_process');
const proc = spawn(
  process.env.HOME + '/.cache/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-linux64/chrome-headless-shell',
  [
    '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage',
    `--remote-debugging-port=${CDP_PORT}`,
    '--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1',
    'about:blank',
  ],
  { stdio: ['ignore', 'ignore', 'pipe'] }
);
let wsUrl;
for (let i = 0; i < 150 && !wsUrl; i++) {
  try {
    const list = await (await fetch(`http://127.0.0.1:${CDP_PORT}/json/list`)).json();
    const page = list.find((t) => t.type === 'page');
    if (page) wsUrl = page.webSocketDebuggerUrl;
  } catch { /* devtools http not up yet */ }
  if (!wsUrl) await sleep(100);
}
if (!wsUrl) { console.error('FATAL: browser did not start'); process.exit(2); }

const ws = new WebSocket(wsUrl);
await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });

let msgId = 0;
const pending = new Map();
const events = [];
ws.onmessage = (ev) => {
  const msg = JSON.parse(ev.data);
  if (msg.id && pending.has(msg.id)) {
    const { resolve, reject } = pending.get(msg.id);
    pending.delete(msg.id);
    msg.error ? reject(new Error(JSON.stringify(msg.error))) : resolve(msg.result);
  } else if (msg.method) {
    events.push(msg);
  }
};
const send = (method, params = {}) =>
  new Promise((resolve, reject) => {
    const id = ++msgId;
    pending.set(id, { resolve, reject });
    ws.send(JSON.stringify({ id, method, params }));
  });
const evalJs = async (expression) => {
  const r = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
  if (r.exceptionDetails) throw new Error('page JS error: ' + JSON.stringify(r.exceptionDetails).slice(0, 400));
  return r.result.value;
};
const waitFor = async (expr, timeoutMs = 8000, interval = 200) => {
  const start = Date.now();
  for (;;) {
    const v = await evalJs(expr);
    if (v) return v;
    if (Date.now() - start > timeoutMs) throw new Error('waitFor timeout: ' + expr);
    await sleep(interval);
  }
};

await send('Page.enable');
await send('Runtime.enable');
await send('Page.navigate', { url: PDP });
await waitFor('document.readyState === "complete"');

// recorder installed after load, before any submit interaction
await evalJs(`window.__gqlCalls = [];
  const orig = window.fetch;
  window.fetch = function (url, opts) {
    if (String(url).includes('/graphql')) {
      window.__gqlCalls.push({ url: String(url), body: opts && opts.body ? String(opts.body) : '' });
    }
    return orig.apply(this, arguments);
  }; true`);

// scroll review form into viewport -> triggers Alpine x-defer="intersect"
await evalJs(`document.querySelector('#review_form').scrollIntoView({block:'center'}); true`);
await waitFor(`(() => { const f = document.querySelector('#review_form');
  return (f && f._x_dataStack) ? true : false; })()`, 10000);
check('setup-alpine-init', true, 'Alpine component initialized (x-defer intersect)');

const snap = `(() => {
  const f = document.querySelector('#review_form');
  const msg = f.querySelector('p[class*="text-red"]');
  const btn = f.querySelector('button[type="submit"]');
  const banner = document.body.innerText.includes('ReCaptcha validation failed')
              || document.body.innerText.includes('Xác thực ReCaptcha thất bại');
  if (!btn) return null;
  const mr = msg ? msg.getBoundingClientRect() : null;
  const br = btn.getBoundingClientRect();
  return {
    msgShown: !!msg, msgText: msg ? msg.textContent.trim() : '',
    msgAboveButton: !!(msg && br.width > 0 && mr.bottom <= br.top + 1),
    btnDisabled: btn.disabled || btn.hasAttribute('disabled'),
    btnVisible: br.width > 0,
    tokenField: !!f.elements['g-recaptcha-response'],
    tokenValueLen: f.elements['g-recaptcha-response'] ? f.elements['g-recaptcha-response'].value.length : -1,
    banner, gql: window.__gqlCalls.length,
  };
})()`;
const submit = async () => {
  await evalJs(`document.querySelector('#review_form').requestSubmit(); true`);
  await sleep(600); // Alpine reactivity flush
};
const fill = () => evalJs(`(() => {
  const f = document.querySelector('#review_form');
  const radio = f.querySelector('input[name^="ratings["]');
  radio.checked = true;
  document.getElementById('nickname_field').value = 'QC NGX81D';
  document.getElementById('summary_field').value = 'QC NGX81D review form validation test';
  document.getElementById('review_field').value = 'BUG-NGX81D: submit button must stay usable after failed validation.';
  return true;
})()`);

if (MODE === 'A') {
  // --- AC-001 (captcha disabled): empty submit ----------------------------
  await submit();
  let s = await evalJs(snap);
  check('AC001-no-native-block-reached-handler', s.msgShown === true,
    `empty submit reached custom handler (novalidate flow), inline message shown`);
  check('AC001-message-text', /Vui lòng kiểm tra bạn đã nhập đủ thông tin bắt buộc/.test(s.msgText),
    `message: "${s.msgText}"`);
  check('AC001-message-above-button', s.msgAboveButton === true,
    'inline error renders ABOVE the submit button (QA-confirmed position)');
  check('AC001-button-not-disabled', s.btnDisabled === false && s.btnVisible === true,
    `submit button NOT locked (disabled=${s.btnDisabled}, visible=${s.btnVisible}) — ticket symptom gone`);
  check('AC001-no-vendor-banner', s.banner === false,
    'no "ReCaptcha validation failed" global banner (vendor lock path not reached)');
  check('AC001-no-graphql', s.gql === 0, `graphql calls after empty submit: ${s.gql}`);

  // --- AC-006 (captcha disabled): fill -> resubmit without refresh --------
  await fill();
  await submit();
  await waitFor(`window.__gqlCalls.length > 0 ? true : false`, 15000);
  await sleep(400);
  s = await evalJs(`(() => {
    const ok = document.querySelector('#review_form p[class*="text-green"]');
    const err = document.querySelector('#review_form p[class*="text-red"]');
    return { gql: window.__gqlCalls.length, success: ok ? ok.textContent.trim() : '',
             error: err ? err.textContent.trim() : '',
             btnDisabled: document.querySelector('#review_form button[type="submit"]').disabled };
  })()`);
  check('AC006-flow-unchanged-graphql-runs', s.gql === 1,
    `captcha disabled: submit went through to GraphQL (calls: ${s.gql}) — no pre-check blocking`);
  check('AC006-button-still-usable', s.btnDisabled === false, 'button never locked during/after flow');
  check('AC006-outcome', /Đánh giá.*đã được gửi và chờ duyệt/.test(s.success),
    s.success ? `success: "${s.success}"` : `ERROR: "${s.error}"`);
} else {
  // --- AC-001B (captcha enabled): empty submit ----------------------------
  await submit();
  let s = await evalJs(snap);
  check('AC001B-field-message', /Vui lòng kiểm tra bạn đã nhập đủ thông tin bắt buộc/.test(s.msgText),
    `empty submit: field message "${s.msgText}"`);
  check('AC001B-button-not-disabled', s.btnDisabled === false,
    `button NOT locked (disabled=${s.btnDisabled}) — vendor lock line exists in HTML but unreachable`);
  check('AC001B-no-banner-no-graphql', s.banner === false && s.gql === 0,
    `banner=${s.banner}, graphql=${s.gql}`);

  // --- AC-002 (captcha enabled): fields filled, token missing -------------
  await fill();
  await submit();
  s = await evalJs(snap);
  check('AC002-no-token', s.tokenField === false || s.tokenValueLen === 0,
    `no usable token (field present=${s.tokenField}, value length=${s.tokenValueLen}) — Google injects an empty textarea with the dummy key; pre-check catches both flavors`);
  check('AC002-captcha-message', /Vui lòng xác thực reCAPTCHA trước khi gửi đánh giá/.test(s.msgText),
    `captcha inline message replaces field message: "${s.msgText}"`);
  check('AC002-message-above-button', s.msgAboveButton === true,
    'captcha message renders ABOVE the submit button');
  check('AC002-button-not-disabled', s.btnDisabled === false,
    'button NOT locked — customer can tick captcha and resubmit without reload');
  check('AC002-no-banner-no-graphql', s.banner === false && s.gql === 0,
    `banner=${s.banner}, graphql=${s.gql} (early return before vendor JS + placeReview)`);
}

// summary ----------------------------------------------------------------
const failed = results.filter((r) => !r.ok);
console.log('\nSUMMARY [' + MODE + ']: ' + (results.length - failed.length) + '/' + results.length + ' checks passed');
console.log(JSON.stringify(results, null, 2));
ws.close();
proc.kill();
process.exit(failed.length ? 1 : 0);
