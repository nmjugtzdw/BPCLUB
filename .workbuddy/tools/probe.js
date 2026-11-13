/**
 * Minimal CDP probe: navigate and dump what Chrome actually rendered.
 * Usage: node probe.js <url> [theme]
 */
'use strict';

const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const CHROME = 'C:/Users/Administrator/AppData/Local/Google/Chrome/Bin/chrome.exe';
const PORT = 9337;
const url = process.argv[2];
const theme = process.argv[3] || 'light';
if (!url) {
  console.error('usage: node probe.js <url> [light|dark]');
  process.exit(2);
}

const userDataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'kwl-probe-'));
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

(async () => {
  const chrome = spawn(CHROME, [
    '--headless=new', '--disable-gpu', '--proxy-server=http://127.0.0.1:9776', '--no-sandbox', '--no-first-run',
    '--no-default-browser-check', '--disable-extensions', '--hide-scrollbars',
    '--force-device-scale-factor=1', `--user-data-dir=${userDataDir}`,
    `--remote-debugging-port=${PORT}`, 'about:blank',
  ], { stdio: 'ignore' });

  let cdp;
  try {
    await waitForEndpoint();
    const target = await (await fetch(`http://127.0.0.1:${PORT}/json/new?about:blank`, { method: 'PUT' })).json();
    cdp = await connect(target.webSocketDebuggerUrl);
    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');
    await cdp.send('Network.enable');
    await cdp.send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false });
    if (theme === 'dark') {
      await cdp.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'dark' }] });
    }
    const nav = await cdp.send('Page.navigate', { url });
    await sleep(3000);
    const out = await cdp.send('Runtime.evaluate', {
      expression: `(() => ({
        title: document.title,
        href: location.href,
        readyState: document.readyState,
        htmlLen: document.documentElement.outerHTML.length,
        innerText: (document.body ? document.body.innerText : '').slice(0, 300),
        scriptCount: document.querySelectorAll('script[src]').length,
        cssLinks: Array.from(document.querySelectorAll('link[rel=stylesheet]')).map((l) => l.href),
        themeAttr: document.documentElement.getAttribute('data-theme'),
        bodyBg: getComputedStyle(document.body).backgroundColor
      }))()`,
      returnByValue: true,
    });
    console.log(JSON.stringify({ nav, ...out.result.value }, null, 2));
  } finally {
    try { if (cdp) cdp.ws.close(); } catch (e) {}
    try { chrome.kill(); } catch (e) {}
    await sleep(300);
    try { fs.rmSync(userDataDir, { recursive: true, force: true }); } catch (e) {}
  }
})().catch((e) => { console.error('FAILED: ' + e.message); process.exit(1); });
