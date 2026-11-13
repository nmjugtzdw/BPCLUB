/**
 * P06 审计：CSP 违规、横向溢出、键盘焦点可见性、表单标签。
 *
 * 与 cdp-check.js 一样零依赖（Node 22 自带 fetch / WebSocket）。
 * 本机访问 kwl.zzzmxxkj.com 必须显式带 --proxy-server，Chrome 不读 https_proxy。
 *
 * 用法：
 *   node p06-audit.js <path,path,...> [--login=user:pass]
 * 输出：JSON 数组，每项含 path/viewport/overflow/focus/labels/csp
 */
'use strict';

const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const CHROME = process.env.CHROME_PATH
  || 'C:/Users/Administrator/AppData/Local/Google/Chrome/Bin/chrome.exe';
const PORT = Number(process.env.CDP_PORT || 9341);
const BASE = process.env.KWL_BASE || 'https://kwl.zzzmxxkj.com';

const args = process.argv.slice(2);
const paths = (args.find((a) => !a.startsWith('--')) || '/').split(',').filter(Boolean);
const loginArg = (args.find((a) => a.startsWith('--login=')) || '').slice('--login='.length);
const viewports = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'mobile', width: 375, height: 812 },
];

const userDataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'kwl-p06-'));

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function waitForEndpoint(retries = 60) {
  for (let i = 0; i < retries; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${PORT}/json/version`);
      if (res.ok) return res.json();
    } catch (e) { /* not ready */ }
    await sleep(250);
  }
  throw new Error('DevTools endpoint never became ready');
}

class Cdp {
  constructor(ws) {
    this.ws = ws;
    this.id = 0;
    this.pending = new Map();
    ws.addEventListener('message', (event) => {
      const msg = JSON.parse(event.data);
      if (msg.id && this.pending.has(msg.id)) {
        const { resolve, reject } = this.pending.get(msg.id);
        this.pending.delete(msg.id);
        if (msg.error) reject(new Error(JSON.stringify(msg.error)));
        else resolve(msg.result);
      }
    });
  }

  send(method, params = {}) {
    const id = ++this.id;
    this.ws.send(JSON.stringify({ id, method, params }));
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      setTimeout(() => {
        if (this.pending.has(id)) {
          this.pending.delete(id);
          reject(new Error(`timeout: ${method}`));
        }
      }, 30000);
    });
  }
}

function connect(wsUrl) {
  return new Promise((resolve, reject) => {
    const ws = new WebSocket(wsUrl);
    ws.addEventListener('open', () => resolve(new Cdp(ws)));
    ws.addEventListener('error', (e) => reject(new Error('ws error: ' + (e.message || 'unknown'))));
  });
}

async function evaluate(cdp, expression) {
  const res = await cdp.send('Runtime.evaluate', {
    expression,
    returnByValue: true,
    awaitPromise: true,
  });
  if (res.exceptionDetails) {
    throw new Error('evaluate failed: ' + JSON.stringify(res.exceptionDetails.text || ''));
  }
  return res.result.value;
}

/**
 * 导航并确认真的到达目标 origin。
 *
 * 本机 Chrome 冷启动后头几秒导航会落到 chrome-error:// 错误页；错误页同样能
 * 取到 DOM，若不校验就会把错误页当页面审计，得出「0 溢出 0 违规」的假通过。
 */
async function goto(cdp, url, attempts = 5) {
  for (let i = 1; i <= attempts; i++) {
    await cdp.send('Page.navigate', { url });
    await sleep(1200);
    const state = await evaluate(cdp, `({ href: location.href, ready: document.readyState })`);
    if (state.href.startsWith('https://kwl.zzzmxxkj.com')) {
      return { ok: true, href: state.href, attempts: i };
    }
    await sleep(1200);
  }
  return { ok: false, href: 'unreachable', attempts };
}

const PAGE_PROBE = `(() => {
  const de = document.documentElement;
  const vw = window.innerWidth;
  const offenders = [];
  document.querySelectorAll('body *').forEach((el) => {
    const r = el.getBoundingClientRect();
    if (r.width === 0 && r.height === 0) return;
    if (r.right > vw + 1 || r.left < -1) {
      offenders.push({
        tag: el.tagName.toLowerCase(),
        cls: (el.className && String(el.className).slice(0, 50)) || '',
        left: Math.round(r.left), right: Math.round(r.right),
      });
    }
  });
  const unlabeled = [];
  document.querySelectorAll('input,select,textarea').forEach((el) => {
    // 隐藏域（如 CSRF 令牌）不需要可见标签。
    if (el.type === 'hidden') return;
    const hasLabel = (el.labels && el.labels.length > 0)
      || el.getAttribute('aria-label') || el.getAttribute('aria-labelledby');
    if (!hasLabel) unlabeled.push(el.name || el.id || el.tagName.toLowerCase());
  });
  return {
    title: document.title,
    onOrigin: location.host === 'kwl.zzzmxxkj.com',
    scrollWidth: de.scrollWidth,
    clientWidth: de.clientWidth,
    overflowPx: Math.max(0, de.scrollWidth - vw),
    offenders: offenders.slice(0, 8),
    unlabeledInputs: unlabeled,
    h1Count: document.querySelectorAll('h1').length,
    cspViolations: (window.__csp || []).slice(0, 10),
  };
})()`;

const CONTRAST_PROBE = `(() => {
  const parse = (c) => {
    const m = c.match(/rgba?\\(([^)]+)\\)/);
    if (!m) return null;
    const p = m[1].split(',').map((v) => parseFloat(v.trim()));
    return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
  };
  const lum = ({ r, g, b }) => {
    const f = (v) => { const s = v / 255; return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  };
  const ratio = (a, b) => { const l1 = lum(a), l2 = lum(b); return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05); };
  const blend = (top, bottom) => ({
    r: top.r * top.a + bottom.r * (1 - top.a),
    g: top.g * top.a + bottom.g * (1 - top.a),
    b: top.b * top.a + bottom.b * (1 - top.a),
    a: 1,
  });
  const effectiveBg = (el) => {
    let node = el, acc = null;
    while (node && node !== document.documentElement) {
      const c = parse(getComputedStyle(node).backgroundColor);
      if (c && c.a > 0) {
        acc = acc === null ? c : blend(acc, c);
        if (acc.a >= 1) return acc;
      }
      node = node.parentElement;
    }
    const rootBg = parse(getComputedStyle(document.body).backgroundColor) || { r: 255, g: 255, b: 255, a: 1 };
    return acc === null ? rootBg : blend(acc, rootBg);
  };
  const results = [], seen = new Set();
  document.querySelectorAll('body *').forEach((el) => {
    const hasOwnText = Array.from(el.childNodes).some((n) => n.nodeType === 3 && n.textContent.trim().length > 1);
    if (!hasOwnText) return;
    const st = getComputedStyle(el);
    if (st.visibility === 'hidden' || st.display === 'none' || parseFloat(st.opacity) < 0.6) return;
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) return;
    const fg = parse(st.color);
    if (!fg) return;
    const bg = effectiveBg(el);
    const text = el.textContent.trim().slice(0, 34);
    const size = parseFloat(st.fontSize);
    const bold = parseInt(st.fontWeight, 10) >= 700;
    const need = (size >= 24 || (size >= 18.66 && bold)) ? 3 : 4.5;
    const cr = ratio(fg.a >= 1 ? fg : blend(fg, bg), bg);
    const key = text + '|' + st.color + '|' + Math.round(size);
    if (seen.has(key)) return;
    seen.add(key);
    results.push({ text, cls: String(el.className || el.tagName).slice(0, 40), ratio: Math.round(cr * 100) / 100, need, pass: cr >= need - 0.05 });
  });
  const failures = results.filter((x) => !x.pass).sort((a, b) => a.ratio - b.ratio);
  return { checked: results.length, failures: failures.slice(0, 8) };
})()`;

const FOCUS_PROBE = `(() => {
  const el = document.activeElement;
  if (!el || el === document.body || el === document.documentElement) {
    return { found: false, tag: 'body' };
  }
  const s = getComputedStyle(el);
  const hasOutline = s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0;
  const hasShadow = s.boxShadow && s.boxShadow !== 'none';
  const rect = el.getBoundingClientRect();
  return {
    found: true,
    tag: el.tagName.toLowerCase(),
    text: (el.textContent || el.getAttribute('aria-label') || '').trim().slice(0, 24),
    href: el.getAttribute && el.getAttribute('href'),
    visible: hasOutline || hasShadow,
    outline: s.outlineStyle + ' ' + s.outlineWidth + ' ' + s.outlineColor,
    boxShadow: s.boxShadow,
    inViewport: rect.top >= -1 && rect.bottom <= window.innerHeight + 1,
    obscured: rect.width === 0 || rect.height === 0,
  };
})()`;

(async () => {
  const chromeArgs = [
    '--headless=new',
    '--disable-gpu',
    '--no-sandbox',
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-extensions',
    '--hide-scrollbars',
    `--user-data-dir=${userDataDir}`,
    `--remote-debugging-port=${PORT}`,
    'about:blank',
  ];
  // 本机代理是可选的外部工具：设置 KWL_PROXY 才走代理，否则直连。
  if (process.env.KWL_PROXY) {
    chromeArgs.unshift('--proxy-server=' + process.env.KWL_PROXY);
  }
  const chrome = spawn(CHROME, chromeArgs, { stdio: 'ignore' });

  let cdp;
  let ws;
  const report = [];
  try {
    await waitForEndpoint();
    const target = await (await fetch(`http://127.0.0.1:${PORT}/json/new?about:blank`, { method: 'PUT' })).json();
    cdp = await connect(target.webSocketDebuggerUrl);
    ws = cdp.ws;

    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');
    const theme = process.env.THEME === 'dark' ? 'dark' : 'light';
    if (theme === 'dark') {
      await cdp.send('Emulation.setEmulatedMedia', {
        features: [{ name: 'prefers-color-scheme', value: 'dark' }],
      });
    }
    // 记录 CSP 违规：必须在任何页面脚本之前注入。
    await cdp.send('Page.addScriptToEvaluateOnNewDocument', {
      source: `window.__csp = []; document.addEventListener('securitypolicyviolation', (e) => {
        window.__csp.push({ directive: e.violatedDirective, blocked: (e.blockedURI || '').slice(0, 80) });
      });`,
    });

    if (loginArg) {
      const [user, pass] = loginArg.split(':');
      await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: 1440, height: 900, deviceScaleFactor: 1, mobile: false,
      });
      // 导航到 /login 在冷启动时不稳定；改为先打开首页，再在同源上下文里
      // 用 fetch 取表单令牌并提交，等价于用户操作，且不受导航时序影响。
      let logged = { status: 0, signedIn: false, reason: 'not-attempted' };
      for (let attempt = 1; attempt <= 3; attempt++) {
        const nav = await goto(cdp, BASE + '/');
        if (!nav.ok) {
          logged = { status: 0, signedIn: false, reason: 'navigation-failed', attempt };
          continue;
        }
        logged = await evaluate(cdp, `(async () => {
          if (location.protocol !== 'https:') {
            return { status: 0, signedIn: false, reason: 'not-on-origin', href: location.href };
          }
          const page = await fetch('/login');
          const html = await page.text();
          const m = html.match(/name="csrf" value="([^"]+)"/);
          if (!m) {
            return { status: 0, signedIn: false, reason: 'no-token-in-html', len: html.length };
          }
          const body = new URLSearchParams({ csrf: m[1], identity: ${JSON.stringify(user)}, password: ${JSON.stringify(pass)} });
          const res = await fetch('/login', { method: 'POST', body, redirect: 'manual' });
          const home = await (await fetch('/')).text();
          return { status: res.status, signedIn: home.includes('退出') };
        })()`);
        logged.attempt = attempt;
        if (logged.signedIn) break;
        await sleep(800);
      }
      report.push({ step: 'login', ...logged });
    }

    for (const viewport of viewports) {
      await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: viewport.width,
        height: viewport.height,
        deviceScaleFactor: 1,
        mobile: viewport.width < 600,
      });

      for (const p of paths) {
        const nav = await goto(cdp, BASE + p);
        if (!nav.ok) {
          report.push({ path: p, url: BASE + p, viewport: viewport.name, reachable: false, navAttempts: nav.attempts });
          continue;
        }
        await sleep(600);

        const probe = await evaluate(cdp, PAGE_PROBE);
        const contrast = await evaluate(cdp, CONTRAST_PROBE);

        // 键盘走查：从页面顶部连续 Tab，记录每一步焦点是否可见。
        await evaluate(cdp, `(() => { document.body.focus(); if (document.activeElement) document.activeElement.blur(); window.scrollTo(0,0); })()`);
        const focusSteps = [];
        for (let i = 0; i < 12; i++) {
          await cdp.send('Input.dispatchKeyEvent', {
            type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9,
          });
          await cdp.send('Input.dispatchKeyEvent', {
            type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9,
          });
          await sleep(60);
          focusSteps.push(await evaluate(cdp, FOCUS_PROBE));
        }

        const reachable = focusSteps.filter((s) => s.found && !s.obscured);
        const invisible = reachable.filter((s) => !s.visible);

        report.push({
          path: p,
          url: BASE + p,
          viewport: viewport.name,
          theme,
          reachable: true,
          navAttempts: nav.attempts,
          onOrigin: probe.onOrigin,
          title: probe.title,
          overflowPx: probe.overflowPx,
          offenders: probe.offenders,
          unlabeledInputs: probe.unlabeledInputs,
          h1Count: probe.h1Count,
          cspViolations: probe.cspViolations,
          focusReachable: reachable.length,
          focusInvisible: invisible.length,
          focusInvisibleDetail: invisible.slice(0, 5),
          focusSequence: reachable.slice(0, 6).map((s) => s.tag + ':' + (s.text || '')),
          contrastChecked: contrast.checked,
          contrastFailures: contrast.failures,
        });
      }
    }

    console.log(JSON.stringify(report, null, 2));
  } finally {
    try { if (ws) ws.close(); } catch (e) { /* ignore */ }
    try { chrome.kill(); } catch (e) { /* ignore */ }
    await sleep(400);
    try { fs.rmSync(userDataDir, { recursive: true, force: true }); } catch (e) { /* ignore */ }
  }
})().catch((err) => {
  console.error('FAILED: ' + err.message);
  process.exit(1);
});
