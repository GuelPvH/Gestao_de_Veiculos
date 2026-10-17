import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import { randomBytes } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const configPath = process.env.FLEET_BROWSER_CONFIG;
const reportDir = process.env.FLEET_BROWSER_REPORT_DIR;
if (!configPath || !reportDir || process.env.FLEET_BROWSER_DISPOSABLE !== '1') {
  throw new Error('Use configuração privada, diretório de relatório e FLEET_BROWSER_DISPOSABLE=1.');
}
const stat = await fs.stat(configPath);
if ((stat.mode & 0o077) !== 0) throw new Error('A configuração privada deve ter modo 600.');
const repository = path.resolve('.');
if ([configPath, reportDir].some(candidate => path.resolve(candidate).startsWith(repository + path.sep))) {
  throw new Error('Configuração e relatório devem ficar fora do repositório.');
}
const config = JSON.parse(await fs.readFile(configPath, 'utf8'));
const base = new URL(config.url);
if (!['127.0.0.1', 'localhost'].includes(base.hostname) || base.protocol !== 'http:') {
  throw new Error('O teste de gravação aceita somente aplicação descartável em loopback.');
}
if (!/^[a-z0-9-]+$/.test(config.composeProject || '')) throw new Error('Identifique o projeto Compose descartável.');
const container = `${config.composeProject}-app-1`;
const environment = JSON.parse(execFileSync('docker', ['inspect', '--format', '{{json .Config.Env}}', container], { encoding: 'utf8' }));
const values = Object.fromEntries(environment.map(item => item.split(/=(.*)/s).slice(0, 2)));
const ports = JSON.parse(execFileSync('docker', ['inspect', '--format', '{{json .NetworkSettings.Ports}}', container], { encoding: 'utf8' }));
if (values.DB_HOST !== 'mysql-local' || values.DB_DATABASE !== 'frota_pf_local'
  || !ports['8080/tcp']?.some(port => port.HostIp === '127.0.0.1' && port.HostPort === base.port)) {
  throw new Error('O navegador não aponta para o Compose/MySQL local descartável esperado.');
}
await fs.mkdir(reportDir, { recursive: true, mode: 0o700 });
const browser = await chromium.launch({ executablePath: process.env.FLEET_CHROMIUM_PATH || undefined, args: ['--no-sandbox'] });
const errors = [];
const checks = [];
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
const server = await context.newPage();
server.on('pageerror', error => errors.push({ page: 'servidor', message: error.message }));

async function login(page, role) {
  await page.goto(new URL('/entrar', base).toString());
  await page.locator('[name=identificador]').fill(config.users[role].identificador);
  await page.locator('[name=senha]').fill(config.users[role].senha);
  const submitted = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/entrar');
  await page.getByRole('button', { name: 'Acessar' }).click();
  assert.equal((await submitted).status(), 302, 'O login real deve redirecionar após validar a identidade.');
  await page.waitForURL('**/painel');
  assert.match(await page.locator('#fleet-menu a[href$="/selecionar-perfil"]').innerText(), new RegExp(role === 'gestor' ? 'Gestor' : 'Servidor'));
  if (config.users[role].nome) assert.match(await page.locator('.account-button').innerText(), new RegExp(config.users[role].nome));
}

async function reviewAndConfirm(page) {
  await page.getByRole('button', { name: 'Revisar', exact: true }).click();
  const modal = page.locator('#operation-review');
  await modal.waitFor({ state: 'visible' });
  assert.equal(await modal.getByRole('button', { name: 'Confirmar', exact: true }).isEnabled(), true);
  await Promise.all([page.waitForNavigation(), modal.getByRole('button', { name: 'Confirmar', exact: true }).click()]);
  assert.match(page.url(), new RegExp(`^${base.origin}/`));
  assert.doesNotMatch(await page.locator('main').innerText(), /A operação não pôde ser concluída|Não foi possível criar/);
}

function localDate(offsetMinutes) {
  const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
    timeZone: 'America/Porto_Velho', year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).formatToParts(new Date(Date.now() + offsetMinutes * 60000)).map(part => [part.type, part.value]));
  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

try {
  const managerContext = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
  const manager = await managerContext.newPage();
  manager.on('pageerror', error => errors.push({ page: 'gestor', message: error.message }));
  await login(manager, 'gestor');
  const seed = randomBytes(4);
  const plate = `QA${'ABCDEF'[seed[0] % 6]}${seed[1] % 10}${'ABCDEF'[seed[2] % 6]}${String(seed[3] % 100).padStart(2, '0')}`;
  await manager.goto(new URL('/frota/novo', base).toString());
  await manager.locator('[name=categoria_id]').selectOption('1');
  await manager.locator('[name=nome]').fill('Veículo QA navegador');
  await manager.locator('[name=placa]').fill(plate);
  await manager.locator('[name=capacidade]').fill('4');
  await reviewAndConfirm(manager);
  assert.match(await manager.locator('main').innerText(), /Veículo cadastrado/);

  await login(server, 'servidor');
  // O cenário local seleciona sugestões reais da interface sem depender da rede externa.
  await server.route('https://photon.komoot.io/**', async route => {
    const name = new URL(route.request().url()).searchParams.get('q');
    await route.fulfill({ json: { features: [{ geometry: { type: 'Point', coordinates: [-63.90, -8.76] }, properties: { name, city: 'Porto Velho', state: 'Rondônia', countrycode: 'BR' } }] } });
  });
  await server.goto(new URL('/solicitacoes/novo', base).toString());
  const destination = `Unidade QA ${Date.now()}`;
  for (const [field, value] of Object.entries({
    finalidade: 'Inspeção de homologação isolada', origem: 'Sede', destino: destination,
    saida_prevista: localDate(-120), retorno_previsto: localDate(120),
  })) await server.locator(`[name=${field}]`).fill(value);
  for (const field of ['origem', 'destino']) {
    await server.locator(`#location-results-${field}`).getByRole('option').first().click();
  }
  await server.getByRole('button', { name: 'Continuar', exact: true }).click();
  await server.getByRole('button', { name: 'Continuar', exact: true }).click();
  await server.locator('[name=veiculo_pretendido_id]').selectOption({ label: await server.locator(`[name=veiculo_pretendido_id] option:has-text("${plate}")`).innerText() });
  await server.getByRole('button', { name: 'Continuar', exact: true }).click();
  await reviewAndConfirm(server);
  const requestId = Number(new URL(server.url()).pathname.match(/^\/solicitacoes\/(\d+)$/)?.[1]);
  assert.ok(requestId > 0);
  assert.match(await server.locator('main').innerText(), /Rascunho criado/);
  await server.reload();
  assert.match(await server.locator('main').innerText(), /Rascunho/);

  await server.getByRole('button', { name: 'Revisar envio', exact: true }).click();
  await Promise.all([server.waitForNavigation(), server.locator('#request-send').getByRole('button', { name: 'Confirmar', exact: true }).click()]);
  assert.match(await server.locator('main').innerText(), /Solicitação enviada para análise/);
  await server.reload();
  assert.match(await server.locator('main').innerText(), /Aguardando análise/);

  await manager.goto(new URL(`/solicitacoes/${requestId}`, base).toString());
  assert.match(await manager.locator('main').innerText(), new RegExp(destination));
  await manager.getByRole('button', { name: 'Aprovar solicitação', exact: true }).click();
  await manager.locator('[name=veiculo_confirmado_id]').selectOption({ label: await manager.locator(`[name=veiculo_confirmado_id] option:has-text("${plate}")`).innerText() });
  await manager.locator('[name=motorista_confirmado_id]').selectOption({ label: await manager.locator('[name=motorista_confirmado_id] option').filter({ hasText: (config.users.motorista || config.users.servidor).nome }).innerText() });
  await manager.locator('#request-approve [name=justificativa]').fill('Agenda e habilitação conferidas no ambiente isolado.');
  await Promise.all([manager.waitForNavigation(), manager.locator('#request-approve').getByRole('button', { name: 'Confirmar aprovação', exact: true }).click()]);
  assert.match(await manager.locator('main').innerText(), /viagem programada/);
  await manager.reload();
  assert.match(await manager.locator('main').innerText(), /Aprovada/);

  const driverContext = config.users.motorista ? await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' }) : context;
  const driver = config.users.motorista ? await driverContext.newPage() : server;
  if (config.users.motorista) {
    driver.on('pageerror', error => errors.push({ page: 'motorista', message: error.message }));
    await login(driver, 'motorista');
  }
  await driver.goto(new URL('/viagens', base).toString());
  const tripLink = driver.locator('main a[href*="/viagens/"]').first();
  await tripLink.click();
  assert.match(await driver.locator('main').innerText(), new RegExp(destination));
  const tripId = Number(new URL(driver.url()).pathname.match(/^\/viagens\/(\d+)$/)?.[1]);
  assert.ok(tripId > 0);
  assert.match(await driver.locator('main').innerText(), /Programada/);
  const departureKm = 25000 + tripId * 20;

  await driver.getByRole('link', { name: 'Registrar saída' }).click();
  await driver.locator('[name=data_registro]').fill(localDate(-30));
  await driver.locator('[name=quilometragem]').fill(`${departureKm}.0`);
  await driver.locator('[name^=item_]').evaluateAll(nodes => nodes.forEach(node => { node.value = 'ok'; node.dispatchEvent(new Event('change', { bubbles: true })); }));
  await reviewAndConfirm(driver);
  assert.match(await driver.locator('main').innerText(), /Em andamento/);
  await driver.reload();
  assert.match(await driver.locator('main').innerText(), /Saída registrada/);

  await driver.getByRole('link', { name: /Registrar ocorrência/ }).click();
  await driver.locator('[name=tipo]').selectOption('atraso');
  await driver.locator('[name=ocorrido_em]').fill(localDate(-15));
  await driver.locator('[name=descricao]').fill('Ocorrência sintética após a saída.');
  await reviewAndConfirm(driver);
  assert.match(await driver.locator('main').innerText(), /Ocorrência sintética/);

  await driver.getByRole('link', { name: 'Registrar retorno' }).click();
  await driver.locator('[name=data_registro]').fill(localDate(-5));
  await driver.locator('[name=quilometragem]').fill(`${departureKm + 12}.0`);
  await driver.locator('[name^=item_]').evaluateAll(nodes => nodes.forEach(node => { node.value = 'ok'; node.dispatchEvent(new Event('change', { bubbles: true })); }));
  await reviewAndConfirm(driver);
  await driver.reload();
  assert.match(await driver.locator('main').innerText(), /Concluída/);

  const targets = [
    { name: 'painel-servidor', page: server, url: '/painel' },
    { name: 'viagem-concluida', page: driver, url: `/viagens/${tripId}` },
    { name: 'solicitacao-gestor', page: manager, url: `/solicitacoes/${requestId}` },
    { name: 'relatorios-gestor', page: manager, url: '/relatorios' },
  ];
  for (const target of targets) for (const width of [1440, 768, 390, 320]) for (const theme of ['light', 'dark']) {
    await target.page.setViewportSize({ width, height: 900 });
    await target.page.goto(new URL(`${target.url}?tema=${theme}`, base).toString());
    await target.page.evaluate(() => document.fonts.ready);
    const state = await target.page.evaluate(() => ({
      theme: document.documentElement.dataset.bsTheme,
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
      brokenImages: [...document.images].filter(image => !image.complete || image.naturalWidth === 0).length,
      duplicateIds: [...document.querySelectorAll('[id]')].some((node, index, nodes) => nodes.findIndex(other => other.id === node.id) !== index),
    }));
    checks.push({ screen: target.name, width, expectedTheme: theme, ...state });
    if (width === 1440 || width === 390) await target.page.screenshot({ path: path.join(reportDir, `${target.name}-${width}-${theme}.png`), fullPage: true });
    if (width === 1440 || width === 320) {
      await target.page.evaluate(() => { document.documentElement.style.zoom = '2'; });
      const overflow = await target.page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2);
      checks.push({ screen: target.name, width, expectedTheme: theme, theme: state.theme, zoom: 2, overflow, brokenImages: 0, duplicateIds: false });
    }
  }
  const accessibility = [];
  for (const target of targets) {
    await target.page.setViewportSize({ width: 390, height: 900 });
    await target.page.goto(new URL(`${target.url}?tema=light`, base).toString());
    const analysis = await new AxeBuilder({ page: target.page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
    accessibility.push({ screen: target.name, violations: analysis.violations.map(item => ({ id: item.id, targets: item.nodes.map(node => node.target) })) });
  }
  const report = { environment: 'Compose local, MySQL disposable, HTTP real', browser: browser.version(), requestId, tripId,
    separateRequesterAndDriver: Boolean(config.users.motorista),
    flows: ['login', 'create request', 'review/confirm', 'send', 'approve', 'departure/checklist', 'occurrence', 'return'],
    checks, accessibility, errors };
  await fs.writeFile(path.join(reportDir, 'browser-real-report.json'), JSON.stringify(report, null, 2));
  const failures = checks.filter(item => item.theme !== item.expectedTheme || item.overflow || item.brokenImages || item.duplicateIds).length + accessibility.filter(item => item.violations.length).length + errors.length;
  console.log(JSON.stringify({ requestId, tripId, realFlows: report.flows.length, visualChecks: checks.length, axeChecks: accessibility.length, failures }));
  if (failures) process.exitCode = 1;
  if (driverContext !== context) await driverContext.close();
  await managerContext.close();
} finally {
  await browser.close();
}
