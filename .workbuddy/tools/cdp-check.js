/**
 * Minimal Chrome DevTools Protocol driver for visual/a11y checks.
 * No npm packages: Node 22 has a global WebSocket and fetch.
 *
 * Usage: node cdp-check.js <url> <width> <height> [screenshotPath]
 */
'use strict';

const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const CHROME = process.env.CHROME_PATH
  || 'C:/Users/Administrator/AppData/Local/Google/Chrome/Bin/chrome.exe';
const PORT = 9333;

const url = process.argv[2];
const width = Number(process.argv[3] || 1280);
const height = Number(process.argv[4] || 900);
const shotPath = process.argv[5] || '';

if (!url) {
  console.error('usage: node cdp-check.js <url> <width> <height> [screenshotPath]');
  process.exit(2);
}

const userDataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'kwl-cdp-'));

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForEndpoint(retries = 60) {
  for (let i = 0; i < retries; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${PORT}/json/version`);
      if (res.ok) return await res.json();
    } catch (e) {
      // not up yet
    }
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

(async () => {
  const chrome = spawn(CHROME, [
    '--headless=new',
    '--disable-gpu',
    '--proxy-server=http://127.0.0.1:9776',
    '--no-sandbox',
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-extensions',
    '--hide-scrollbars',
    '--force-device-scale-factor=1',
    `--user-data-dir=${userDataDir}`,
    `--remote-debugging-port=${PORT}`,
    'about:blank',
  ], { stdio: 'ignore' });

  let cdp;
  let ws;
  try {
    await waitForEndpoint();
    const list = await (await fetch(`http://127.0.0.1:${PORT}/json/new?about:blank`, { method: 'PUT' })).json();
    cdp = await connect(list.webSocketDebuggerUrl);
    ws = cdp.ws;

    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');
    await cdp.send('Emulation.setDeviceMetricsOverride', {
      width, height, deviceScaleFactor: 1, mobile: width < 600,
    });

    // THEME=dark emulates prefers-color-scheme so the theme toggle's first-load
    // branch is exercised the same way a dark-mode user would hit it.
    if (process.env.THEME === 'dark') {
      await cdp.send('Emulation.setEmulatedMedia', {
        features: [{ name: 'prefers-color-scheme', value: 'dark' }],
      });
    }

    await cdp.send('Page.navigate', { url });
    await sleep(2500);

    const expr = `(() => {
      const de = document.documentElement;
      const vw = window.innerWidth;
      const offenders = [];
      document.querySelectorAll('body *').forEach((el) => {
        const r = el.getBoundingClientRect();
        if (r.width === 0 && r.height === 0) return;
        if (r.right > vw + 1 || r.left < -1) {
          offenders.push({
            tag: el.tagName.toLowerCase(),
            cls: (el.className && String(el.className).slice(0, 60)) || '',
            left: Math.round(r.left),
            right: Math.round(r.right),
            width: Math.round(r.width),
          });
        }
      });
      const style = getComputedStyle(document.body);
      return {
        title: document.title,
        viewportWidth: vw,
        scrollWidth: de.scrollWidth,
        clientWidth: de.clientWidth,
        horizontalOverflow: de.scrollWidth > vw + 1,
        overflowPx: Math.max(0, de.scrollWidth - vw),
        bodyBg: style.backgroundColor,
        bodyColor: style.color,
        docHeight: de.scrollHeight,
        offenders: offenders.slice(0, 12),
        unlabeledInputs: Array.from(document.querySelectorAll('input,select,textarea'))
          .filter((el) => !el.labels?.length && !el.getAttribute('aria-label') && !el.getAttribute('aria-labelledby')).length,
        imagesWithoutAlt: Array.from(document.querySelectorAll('img'))
          .filter((el) => !el.hasAttribute('alt')).length,
        h1Count: document.querySelectorAll('h1').length,
        links: document.querySelectorAll('a[href]').length,
        emptyLinks: Array.from(document.querySelectorAll('a[href]'))
          .filter((el) => !el.textContent.trim() && !el.getAttribute('aria-label')).length,
      };
    })()`;

    const result = await cdp.send('Runtime.evaluate', { expression: expr, returnByValue: true });
    const data = result.result.value;
    console.log(JSON.stringify(data, null, 2));

    if (shotPath) {
      const shot = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
      fs.writeFileSync(shotPath, Buffer.from(shot.data, 'base64'));
      console.error('screenshot: ' + shotPath);
    }
  } finally {
    try { if (ws) ws.close(); } catch (e) {}
    try { chrome.kill(); } catch (e) {}
    await sleep(400);
    try { fs.rmSync(userDataDir, { recursive: true, force: true }); } catch (e) {}
  }
})().catch((err) => {
  console.error('FAILED: ' + err.message);
  process.exit(1);
});
