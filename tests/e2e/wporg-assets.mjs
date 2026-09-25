// Makes the wordpress.org listing assets in .wordpress-org/ (icon, banner, screenshots) with
// headless Chromium. Needs a site from `KEEP=1 tests/e2e/run.sh`; run by tests/e2e/wporg-assets.sh.
import { chromium } from 'playwright';

const url = process.env.WP_URL;
const out = '/out';
const font = '<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;900&display=swap" rel="stylesheet">';
const css = `
  * { margin: 0; box-sizing: border-box; }
  body { font-family: Vazirmatn, sans-serif; }
  .bg { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
        background: radial-gradient(circle at 20% 20%, #2dd4bf 0, #0f766e 45%, #134e4a 100%); color: #fff; }
`;

const browser = await chromium.launch();

async function render(html, width, height, file) {
  const page = await browser.newPage({ viewport: { width, height } });
  await page.setContent(`<!doctype html><html dir="rtl"><head>${font}<style>${css}</style></head><body style="width:${width}px;height:${height}px">${html}</body></html>`);
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${out}/${file}` });
  await page.close();
}

// Icon: the letter ن with a half space mark, on the brand gradient.
for (const size of [128, 256]) {
  await render(`<div class="bg" style="border-radius:${size * 0.18}px">
      <div style="font-weight:900;font-size:${size * 0.62}px;line-height:1;margin-top:-${size * 0.08}px">ن</div>
    </div>`, size, size, `icon-${size}x${size}.png`);
}

// Banner: name, what it does, and a before/after example.
for (const [w, h] of [[772, 250], [1544, 500]]) {
  const k = w / 772;
  await render(`<div class="bg" style="flex-direction:column;gap:${10 * k}px">
      <div style="font-weight:900;font-size:${64 * k}px;line-height:1.1">نگارش</div>
      <div dir="ltr" style="font-size:${20 * k}px;opacity:.9">Persian typography for WordPress</div>
      <div style="margin-top:${8 * k}px;font-size:${22 * k}px;background:rgba(255,255,255,.14);padding:${6 * k}px ${16 * k}px;border-radius:${8 * k}px">
        کتاب ها را خواندید ? &nbsp;←&nbsp; کتاب‌ها را خواندید؟
      </div>
    </div>`, w, h, `banner-${w}x${h}.png`);
}

// Screenshots from the real site (English admin).
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
await page.goto(`${url}/wp-login.php`);
await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'admin');
await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

await page.goto(`${url}/wp-admin/options-general.php?page=negaresh-options`);
for (const rule of ['fix_question_mark', 'fix_suffix_spacing', 'fix_english_quotes', 'fix_spacing_for_punctuations']) {
  await page.locator(`#negaresh_${rule}`).check();
}
await page.locator('#negaresh-preview-input').fill('او گفت "سلام" ... کتاب ها را خواندید ?');
await page.waitForFunction(() => document.querySelector('#negaresh-preview-output').value !== '', null, { timeout: 15000 });
await page.screenshot({ path: `${out}/screenshot-1.png`, clip: { x: 160, y: 32, width: 1120, height: 860 } });

await page.goto(`${url}/wp-admin/post-new.php`);
await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/editor'), null, { timeout: 30000 });
await page.evaluate(() => {
  if (wp.data.select('core/preferences')) {
    wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
    wp.data.dispatch('core/preferences').set('core', 'welcomeGuide', false);
  }
  wp.data.dispatch('core/editor').editPost({ title: 'نگارش' });
  wp.data.dispatch('core/block-editor').resetBlocks([wp.blocks.createBlock('core/paragraph', { content: 'متن آزمایشی ... کتاب ها را خواندید ?' })]);
  if (wp.data.select('core/interface')) wp.data.dispatch('core/interface').enableComplementaryArea('core', 'edit-post/document');
});
const title = await page.evaluate(() => (window.negareshEditor && window.negareshEditor.title) || 'Negaresh');
const toggle = page.locator('.components-panel__body').filter({ hasText: title }).locator('button.components-panel__body-toggle').first();
await toggle.waitFor({ timeout: 15000 });
if ((await toggle.getAttribute('aria-expanded')) !== 'true') await toggle.click();
await page.locator('.negaresh-fix-now').scrollIntoViewIfNeeded();
await page.screenshot({ path: `${out}/screenshot-2.png` });

await page.goto(`${url}/wp-admin/tools.php?page=negaresh-bulk`);
await page.locator('.negaresh-scan').click();
await page.locator('.negaresh-bulk-results tr').first().waitFor({ timeout: 30000 });
await page.locator('.negaresh-bulk-results summary').first().click();
await page.screenshot({ path: `${out}/screenshot-3.png`, clip: { x: 160, y: 32, width: 1120, height: 560 } });

await browser.close();
console.log('DONE');
