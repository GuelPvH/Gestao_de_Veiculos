import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from '@playwright/test';

// Isolated interface harness: real compiled Blade/bundle, synthetic HTTP state.
// Real password, session, protocol and database behavior are covered by PHP/MySQL tests.
const reportDir = process.env.FLEET_SESSION_BROWSER_REPORT_DIR;
if (!reportDir) throw new Error('Defina FLEET_SESSION_BROWSER_REPORT_DIR para guardar o relatório privado.');
await fs.mkdir(reportDir, { recursive: true, mode: 0o700 });
const manifest = JSON.parse(await fs.readFile('public/build/manifest.json', 'utf8'));
const php = `require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo view('components.auth.session-modal')->render();`;
const modal = execFileSync('php', ['-r', php], {
    encoding: 'utf8',
    env: {
        ...process.env,
        APP_ENV: 'testing',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: ':memory:',
        SESSION_DRIVER: 'array',
        APP_URL: 'http://localhost',
        APP_KEY: `base64:${Buffer.alloc(32, 1).toString('base64')}`,
    },
}).replace('data-session-scope=""', 'data-session-scope="synthetic-session"');
const html = `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/build/${manifest['resources/css/app.css'].file}"></head><body><main><h1>Sessão em teste</h1><form data-dirty-form><input id="draft" aria-label="Rascunho"></form></main>${modal}<script type="module" src="/build/${manifest['resources/js/app.js'].file}"></script></body></html>`;
const browser = await chromium.launch({ args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
const errors = [];
let elapsed = 0,
    deadline = 30 * 60000,
    statuses = 0;
let deferStatus = false,
    deferredStatus,
    statusStarted;
const epoch = Date.parse('2026-10-11T12:00:00Z');
await context.route('**/*', async (route) => {
    const url = new URL(route.request().url());
    if (url.hostname !== 'localhost') return route.abort();
    if (url.pathname.startsWith('/build/')) {
        const file = path.resolve('public', '.' + url.pathname);
        if (!file.startsWith(path.resolve('public/build') + path.sep)) return route.abort();
        const type = file.endsWith('.js')
            ? 'application/javascript'
            : file.endsWith('.css')
              ? 'text/css'
              : 'font/woff2';
        return route.fulfill({ body: await fs.readFile(file), contentType: type });
    }
    const state = () => ({
        state: 'ACTIVE',
        server_time: new Date(epoch + elapsed).toISOString(),
        expires_at: new Date(epoch + deadline).toISOString(),
    });
    if (url.pathname === '/sessao/status') {
        statuses++;
        if (deferStatus) {
            deferStatus = false;
            const oldState = state();
            await new Promise((resolve) => {
                deferredStatus = resolve;
                statusStarted();
            });
            return route.fulfill({ json: oldState });
        }
        return route.fulfill({
            status: elapsed >= deadline ? 401 : 200,
            json: elapsed >= deadline ? { state: 'SESSION_ENDED' } : state(),
        });
    }
    if (url.pathname === '/sessao/reautenticar') {
        if (!route.request().postData()?.includes('CorrectForHarness'))
            return route.fulfill({ status: 422, json: { state: 'INVALID_CREDENTIAL' } });
        deadline = elapsed + 30 * 60000;
        return route.fulfill({ json: { ...state(), mfa_required: false } });
    }
    if (url.pathname === '/entrar')
        return route.fulfill({
            body: '<!doctype html><html><body><h1>Entrar</h1></body></html>',
            contentType: 'text/html',
        });
    if (url.pathname === '/seeded-harness') {
        const initial = JSON.stringify(state()).replaceAll('"', '&quot;');
        const seededHtml = html
            .replace('data-session-initial=""', `data-session-initial="${initial}"`)
            .replace('data-session-scope="synthetic-session"', 'data-session-scope="synthetic-seeded-session"');
        return route.fulfill({ body: seededHtml, contentType: 'text/html' });
    }
    return route.fulfill({ body: html, contentType: 'text/html' });
});
const first = await context.newPage(),
    second = await context.newPage();
await first.clock.install({ time: new Date(epoch) });
await Promise.all(
    [first, second].map(async (page) => {
        page.on('pageerror', (error) => errors.push(error.message));
        await page.goto('http://localhost/security-harness');
        await page.waitForFunction(() => document.querySelector('[data-session-countdown]').textContent === '30:00');
        await page.waitForLoadState('networkidle');
    }),
);
const checks = [];
context.setDefaultTimeout(8000);
try {
    assert.equal(statuses, 1, 'Tabs sharing a session should reuse a fresh status response.');
    checks.push({ test: 'two tabs share one initial status request', passed: true });
    const beforeHidden = statuses;
    await second.evaluate(() => {
        Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => 'hidden' });
        dispatchEvent(new Event('focus'));
    });
    await second.waitForLoadState('networkidle');
    assert.equal(statuses, beforeHidden);
    await second.evaluate(() => {
        delete document.visibilityState;
    });
    checks.push({ test: 'hidden tabs do not fetch session status', passed: true });
    checks.push({ test: 'initial deadline follows server', passed: true });
    elapsed = 29 * 60000;
    await first.clock.fastForward(elapsed);
    // Playwright's clock belongs to the browser context: the first advance updates both tabs.
    for (const page of [first, second]) await page.locator('#fleet-session-warning').waitFor({ state: 'visible' });
    await first.clock.runFor(500);
    assert.equal(
        await first.locator('#fleet-session-password').evaluate((input) => input === document.activeElement),
        true,
    );
    checks.push({ test: 'warning at 29 minutes in both tabs and password focus', passed: true });
    await first.screenshot({ path: path.join(reportDir, 'session-warning-390.png'), fullPage: true });
    await first.locator('[name=senha]').fill('IncorrectForHarness');
    await first.locator('[form=fleet-session-continue]').click();
    await first.locator('[data-session-error]').waitFor({ state: 'visible' });
    assert.equal(deadline, 30 * 60000);
    assert.equal(await first.locator('[name=senha]').inputValue(), '');
    checks.push({ test: 'incorrect password keeps deadline and clears input', passed: true });
    await first.clock.runFor(1);
    deferStatus = true;
    const oldStatusStarted = new Promise((resolve) => {
        statusStarted = resolve;
    });
    await second.evaluate(() => dispatchEvent(new Event('focus')));
    await Promise.race([
        oldStatusStarted,
        new Promise((_, reject) => {
            const timeout = setTimeout(() => reject(new Error('Deferred status request did not start')), 8000);
            timeout.unref();
        }),
    ]);
    const renewal = first.waitForResponse(
        (response) => new URL(response.url()).pathname === '/sessao/reautenticar' && response.status() === 200,
    );
    await first.locator('[name=senha]').fill('CorrectForHarness');
    await first.locator('[form=fleet-session-continue]').click();
    await renewal;
    await first.clock.runFor(500);
    try {
        for (const page of [first, second]) await page.locator('#fleet-session-warning').waitFor({ state: 'hidden' });
    } catch (failure) {
        const tabs = await Promise.all(
            [first, second].map((page) =>
                page.evaluate(() => ({
                    countdown: document.querySelector('[data-session-countdown]')?.textContent,
                    visible: document.querySelector('#fleet-session-warning')?.classList.contains('show'),
                    scope: document.querySelector('[data-session-status]')?.dataset.sessionScope,
                    now: Date.now(),
                    cachedStatus: localStorage.getItem('fleet-session-status:synthetic-session'),
                })),
            ),
        );
        process.stderr.write(JSON.stringify({ deadline, statuses, tabs }) + '\n');
        throw failure;
    }
    deferredStatus();
    await second.waitForLoadState('networkidle');
    for (const page of [first, second]) await page.locator('#fleet-session-warning').waitFor({ state: 'hidden' });
    checks.push({ test: 'a late pre-renewal status response cannot undo the new deadline', passed: true });
    assert.equal(deadline, 59 * 60000);
    const stored = await first.evaluate(() => ({
        event: localStorage.getItem('fleet-session-event'),
        other: Object.keys(localStorage),
    }));
    assert.doesNotMatch(stored.event, /CorrectForHarness|IncorrectForHarness|token|csrf/i);
    checks.push({ test: 'renewal synchronizes tabs without storing credentials or tokens', passed: true });
    await first.evaluate(() => {
        Date.now = () => 9999999999999;
    });
    await first.clock.fastForward(1000);
    assert.doesNotMatch(first.url(), /entrar/);
    checks.push({ test: 'altered wall clock does not trigger expiration', passed: true });
    elapsed = 60 * 60000;
    await second.evaluate(() => dispatchEvent(new Event('focus')));
    await second.waitForURL('**/entrar');
    await first.waitForURL('**/entrar');
    checks.push({ test: 'focus after suspension rechecks server and ends both tabs', passed: true });
    elapsed = 0;
    deadline = 30 * 60000;
    await first.goto('http://localhost/security-harness');
    await first.waitForFunction(() => document.querySelector('[data-session-countdown]').textContent === '30:00');
    await first.waitForLoadState('networkidle');
    await first.locator('#draft').fill('Alterações não enviadas');
    await first.clock.fastForward(30 * 60000);
    await first.waitForURL('**/entrar');
    checks.push({ test: 'no answer expires at 30 minutes and unsent form does not prevent logout', passed: true });
    elapsed = 0;
    deadline = 30 * 60000;
    const beforeSeed = statuses;
    await first.goto('http://localhost/seeded-harness');
    await first.waitForFunction(() => document.querySelector('[data-session-countdown]').textContent === '30:00');
    await first.waitForLoadState('networkidle');
    assert.equal(
        statuses,
        beforeSeed,
        'The validated state rendered by the server should avoid a startup status request.',
    );
    checks.push({ test: 'server-rendered initial state avoids redundant startup status request', passed: true });
    assert.deepEqual(errors, []);
    const report = {
        browser: browser.version(),
        source: 'compiled Blade and production bundle; synthetic HTTP responses',
        checks,
        statuses,
        errors,
    };
    await fs.writeFile(path.join(reportDir, 'session-browser-report.json'), JSON.stringify(report, null, 2));
    process.stdout.write(JSON.stringify(report) + '\n');
} finally {
    await browser.close();
}
