/**
 * WCAG contrast audit for the rendered page.
 * Walks every visible text node, resolves its effective background by climbing
 * ancestors, and reports pairs below the AA thresholds (4.5:1 normal, 3:1 large).
 *
 * Usage: node contrast-audit.js <url> <width> <height> [theme]
 */
'use strict';

const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const CHROME = 'C:/Users/Administrator/AppData/Local/Google/Chrome/Bin/chrome.exe';
const PORT = 9334;
const url = process.argv[2];
const width = Number(process.argv[3] || 1280);
const height = Number(process.argv[4] || 900);
const theme = process.argv[5] || 'light';

if (!url) {
  console.error('usage: node contrast-audit.js <url> <width> <height> [light|dark]');
  process.exit(2);
}

const userDataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'kwl-contrast-'));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function waitForEndpoint(retries = 60) {
  for (let i = 0; i < retries; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${PORT}/json/version`);
      if (res.ok) return await res.json();
    } catch (e) {}
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
          reject(new Error('timeout: ' + method));
        }
      }, 30000);
    });
  }
}

function connect(wsUrl) {
  return new Promise((resolve, reject) => {
    const ws = new WebSocket(wsUrl);
    ws.addEventListener('open', () => resolve(new Cdp(ws)));
    ws.addEventListener('error', () => reject(new Error('ws error')));
  });
}

const AUDIT = `(() => {
  const parse = (c) => {
    const m = c.match(/rgba?\\(([^)]+)\\)/);
    if (!m) return null;
    const p = m[1].split(',').map((v) => parseFloat(v.trim()));
    return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
  };
  const lum = ({ r, g, b }) => {
    const f = (v) => {
      const s = v / 255;
      return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  };
  const ratio = (a, b) => {
    const l1 = lum(a), l2 = lum(b);
    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
  };
  const blend = (top, bottom) => ({
    r: top.r * top.a + bottom.r * (1 - top.a),
    g: top.g * top.a + bottom.g * (1 - top.a),
    b: top.b * top.a + bottom.b * (1 - top.a),
    a: 1,
  });
  const effectiveBg = (el) => {
    let node = el;
    let acc = null;
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

  const results = [];
  const seen = new Set();
  document.querySelectorAll('body *').forEach((el) => {
    const hasOwnText = Array.from(el.childNodes).some(
      (n) => n.nodeType === 3 && n.textContent.trim().length > 1
    );
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
    const isLarge = size >= 24 || (size >= 18.66 && bold);
    const need = isLarge ? 3 : 4.5;
    const cr = ratio(fg.a >= 1 ? fg : blend(fg, bg), bg);
    const key = text + '|' + st.color + '|' + Math.round(size);
    if (seen.has(key)) return;
    seen.add(key);
    results.push({
      text,
      cls: (el.className && String(el.className).slice(0, 40)) || el.tagName.toLowerCase(),
      color: st.color,
      bg: 'rgb(' + Math.round(bg.r) + ', ' + Math.round(bg.g) + ', ' + Math.round(bg.b) + ')',
      size: Math.round(size * 10) / 10,
      ratio: Math.round(cr * 100) / 100,
      need,
      pass: cr >= need - 0.05,
    });
  });
  const failures = results.filter((r) => !r.pass).sort((a, b) => a.ratio - b.ratio);
  return { checked: results.length, failures };
})()`;

(async () => {
  const chromeArgs = [
    '--headless=new', '--disable-gpu', '--no-sandbox', '--no-first-run',
    '--no-default-browser-check', '--disable-extensions', '--hide-scrollbars',
    '--force-device-scale-factor=1', `--user-data-dir=${userDataDir}`,
    `--remote-debugging-port=${PORT}`, 'about:blank',
  ];
  // 代理可选：本机代理不在时直连也能通，但绝不能因为错误页而“通过”。
  if (process.env.KWL_PROXY) {
    chromeArgs.splice(2, 0, '--proxy-server=' + process.env.KWL_PROXY);
  }
  const chrome = spawn(CHROME, chromeArgs, { stdio: 'ignore' });

  let cdp;
  try {
    await waitForEndpoint();
    const target = await (await fetch(`http://127.0.0.1:${PORT}/json/new?about:blank`, { method: 'PUT' })).json();
    cdp = await connect(target.webSocketDebuggerUrl);
    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');
    await cdp.send('Emulation.setDeviceMetricsOverride', {
      width, height, deviceScaleFactor: 1, mobile: width < 600,
    });
    if (theme === 'dark') {
      await cdp.send('Emulation.setEmulatedMedia', {
        features: [{ name: 'prefers-color-scheme', value: 'dark' }],
      });
    }
    // 必须确认落到目标站点，否则错误页也会返回 checked/failures 造成假通过。
    let arrived = false;
    for (let attempt = 1; attempt <= 5 && !arrived; attempt++) {
      await cdp.send('Page.navigate', { url });
      await sleep(1800);
      const state = await cdp.send('Runtime.evaluate', {
        expression: 'location.href', returnByValue: true,
      });
      arrived = String(state.result.value || '').startsWith('https://kwl.zzzmxxkj.com');
    }
    if (!arrived) {
      throw new Error('导航未到达目标站点（可能在 Chrome 错误页），本次结果作废');
    }
    const out = await cdp.send('Runtime.evaluate', { expression: AUDIT, returnByValue: true });
    const data = out.result.value;
    console.log(JSON.stringify({ url, theme, width, ...data }, null, 2));
  } finally {
    try { if (cdp) cdp.ws.close(); } catch (e) {}
    try { chrome.kill(); } catch (e) {}
    await sleep(300);
    try { fs.rmSync(userDataDir, { recursive: true, force: true }); } catch (e) {}
  }
})().catch((e) => { console.error('FAILED: ' + e.message); process.exit(1); });
