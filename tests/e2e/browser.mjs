// Drives the Negaresh settings page in headless Chromium (Playwright): the live preview reacts to
// typing and to unsaved checkbox changes, and "Reset rules" asks first. Run by tests/e2e/browser.sh.
import { chromium } from 'playwright';

const url = process.env.WP_URL;
const shots = process.env.SHOTS || '/shots';
const fail = (msg) => { console.log(`FAIL  ${msg}`); process.exitCode = 1; };
const pass = (msg) => console.log(`PASS  ${msg}`);

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 1600 } });
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => {
  // resource failures are reported below with their URL; only the site's own count
  if (m.type() === 'error' && !m.text().startsWith('Failed to load resource')) errors.push(m.text());
});
page.on('requestfailed', (r) => {
  if (r.url().startsWith(url)) errors.push(`${r.url()} ${r.failure()?.errorText}`);
  else console.log(`note  external request failed (ignored): ${r.url()}`);
});

await page.goto(`${url}/wp-login.php`);
await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'admin');
await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
await page.goto(`${url}/wp-admin/options-general.php?page=negaresh-options`);

const output = page.locator('#negaresh-preview-output');
await page.locator('#negaresh-preview-input').fill('<p>سلام ... عدد ٤٥٦</p>');
try {
  await page.waitForFunction(() => document.querySelector('#negaresh-preview-output').value !== '', null, { timeout: 10000 });
  const first = await output.inputValue();
  first === '<p>سلام… عدد ۴۵۶</p>' ? pass('preview shows the fixed text while typing') : fail(`preview gave ${first}`);
} catch (e) {
  fail('preview never answered');
}

await page.locator('#negaresh_fix_three_dots').uncheck();
try {
  await page.waitForFunction(() => document.querySelector('#negaresh-preview-output').value.includes('...'), null, { timeout: 10000 });
  pass('preview follows an unsaved checkbox change');
} catch (e) {
  fail(`preview did not follow the checkbox: ${await output.inputValue()}`);
}

let asked = false;
page.once('dialog', async (dialog) => { asked = true; await dialog.dismiss(); });
await page.locator('.negaresh-reset').click();
await page.waitForTimeout(500);
asked ? pass('reset asks for confirmation') : fail('reset did not ask');
page.url().includes('page=negaresh-options') && !page.url().includes('settings-updated')
  ? pass('dismissing the confirmation does not submit') : fail(`page moved to ${page.url()}`);

await page.screenshot({ path: `${shots}/settings-${process.env.SHOT_NAME || 'page'}.png`, fullPage: true });
errors.length ? fail(`browser errors: ${errors.join(' | ')}`) : pass('no JavaScript errors');
await browser.close();
