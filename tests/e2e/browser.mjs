// Drives the Negaresh settings page in headless Chromium (Playwright): the live preview reacts to
// typing and to unsaved checkbox changes, and "Reset rules" asks first. Run by tests/e2e/browser.sh.
import { chromium } from 'playwright';
import { createRequire } from 'module';

const url = process.env.WP_URL;
const shots = process.env.SHOTS || '/shots';
const fail = (msg) => { console.log(`FAIL  ${msg}`); process.exitCode = 1; };
const pass = (msg) => console.log(`PASS  ${msg}`);

// P3-8: WCAG 2 A/AA check with axe-core on Negaresh's own part of a page.
const axePath = createRequire(import.meta.url).resolve('axe-core');
async function a11y(page, selector, name) {
  await page.addScriptTag({ path: axePath });
  const result = await page.evaluate(async (sel) => {
    const r = await window.axe.run(sel, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } });
    return r.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.length} × ${v.nodes[0].target.join(' ')} ${v.help}`);
  }, selector);
  result.length ? fail(`accessibility, ${name}: ${result.join(' | ')}`) : pass(`accessibility, ${name}: no WCAG 2 A/AA violations`);
}

let browser;
try {
  browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 1600 } });
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => {
    // Uncaught errors always count (pageerror above). Console errors only when they are ours:
    // older WordPress logs its own block validation errors for theme content.
    if (m.type() === 'error' && /negaresh/i.test(m.text())) errors.push(m.text());
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

  // B32: every rule example reads right to left, "before" on the right and the arrow pointing left
  // to "after". wp-admin's `code { direction: ltr }` used to override dir="rtl" (numbers swapped).
  const swapped = await page.evaluate(() => [...document.querySelectorAll('.negaresh-settings td code[dir="rtl"]')]
    .filter((code) => {
      const text = code.firstChild;
      const at = text.data.indexOf(' ← ');
      const range = document.createRange();
      range.setStart(text, 0); range.setEnd(text, at);
      const before = range.getBoundingClientRect().left;
      range.setStart(text, at + 3); range.setEnd(text, text.data.length);
      return getComputedStyle(code).direction !== 'rtl' || before <= range.getBoundingClientRect().left;
    }).map((code) => code.textContent));
  swapped.length === 0 ? pass('rule examples read right to left (B32)') : fail(`rule examples shown left to right (B32): ${swapped.join(' | ')}`);

  // B32: the "words to leave alone" box follows its text (dir="auto"); wp-admin's .code forced LTR.
  await page.locator('#negaresh_protected_words').fill('کتاب‌خانه');
  const wordsDir = await page.locator('#negaresh_protected_words').evaluate((el) => getComputedStyle(el).direction);
  wordsDir === 'rtl' ? pass('words to leave alone: Persian is typed right to left (B32)') : fail(`words to leave alone box is ${wordsDir} for Persian (B32)`);
  await page.locator('#negaresh_protected_words').fill('');

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

  // I10b: the word list as typed on the page (not saved) is used by the preview.
  await page.locator('#negaresh_protected_words').fill('٤٥٦');
  await page.locator('#negaresh-preview-input').fill('کد ٤٥٦ و ٧٨٩');
  try {
    await page.waitForFunction(() => document.querySelector('#negaresh-preview-output').value === 'کد ٤٥٦ و ۷۸۹', null, { timeout: 10000 });
    pass('preview leaves the words typed in the list alone (I10b)');
  } catch (e) {
    fail(`preview with a word list gave: ${await output.inputValue()}`);
  }
  await page.locator('#negaresh_protected_words').fill('');

  let asked = false;
  page.once('dialog', async (dialog) => { asked = true; await dialog.dismiss(); });
  await page.locator('.negaresh-reset').click();
  await page.waitForTimeout(500);
  asked ? pass('reset asks for confirmation') : fail('reset did not ask');
  page.url().includes('page=negaresh-options') && !page.url().includes('settings-updated')
    ? pass('dismissing the confirmation does not submit') : fail(`page moved to ${page.url()}`);

  await a11y(page, '.negaresh-settings', 'settings page');
  await page.screenshot({ path: `${shots}/settings-${process.env.SHOT_NAME || 'page'}.png`, fullPage: true });
  // The top of the page (preview with a fixed example, mode, first rules): the README screenshot.
  for (const rule of ['fix_three_dots', 'fix_question_mark', 'fix_suffix_spacing', 'fix_english_quotes', 'fix_spacing_for_punctuations']) {
    await page.locator(`#negaresh_${rule}`).check(); // on the page only, not saved
  }
  await page.locator('#negaresh-preview-input').fill('او گفت "سلام" ... کتاب ها را خواندید ?');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${shots}/readme-${process.env.SHOT_NAME || 'page'}.png`, clip: { x: 160, y: 32, width: 1120, height: 900 } });

  // I6: the Negaresh panel in the block editor.
  await page.goto(`${url}/wp-admin/post-new.php`);
  await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/editor') && wp.data.select('core/block-editor'), null, { timeout: 30000 });
  // Wait until the editor has set up the new post; inserting earlier can be undone by that setup
  // (seen on slower CI runners: the editor content was empty when the button was pressed).
  await page.waitForFunction(() => {
    const editor = wp.data.select('core/editor');
    const ready = editor.__unstableIsEditorReady ? editor.__unstableIsEditorReady() : true;
    return ready && editor.getCurrentPostId();
  }, null, { timeout: 30000 });
  await page.evaluate(() => {
    // Welcome guide off: core/preferences on current WordPress, a feature toggle on 5.8.
    const prefs = wp.data.select('core/preferences') ? wp.data.dispatch('core/preferences') : null;
    if (prefs) { prefs.set('core/edit-post', 'welcomeGuide', false); prefs.set('core', 'welcomeGuide', false); }
    const editPost = wp.data.select('core/edit-post');
    if (editPost && editPost.isFeatureActive && editPost.isFeatureActive('welcomeGuide')) {
      wp.data.dispatch('core/edit-post').toggleFeature('welcomeGuide');
    }
    const block = wp.blocks.createBlock('core/paragraph', { content: 'سلام ... عدد ٤٥٦' });
    wp.data.dispatch('core/block-editor').resetBlocks([block]);
    if (wp.data.select('core/interface')) {
      wp.data.dispatch('core/interface').enableComplementaryArea('core', 'edit-post/document');
      wp.data.dispatch('core/interface').enableComplementaryArea('core/edit-post', 'edit-post/document');
    }
  });
  // Found by its title: WordPress 5.8 does not pass className through to the panel.
  const panelTitle = await page.evaluate(() => (window.negareshEditor && window.negareshEditor.title) || 'Negaresh');
  const panelToggle = page.locator('.components-panel__body').filter({ hasText: panelTitle }).locator('button.components-panel__body-toggle').first();
  try {
    await panelToggle.waitFor({ timeout: 15000 });
    if ((await panelToggle.getAttribute('aria-expanded')) !== 'true') await panelToggle.click();
    pass('Negaresh panel in the editor sidebar');
  } catch (e) {
    fail('Negaresh panel not found in the editor sidebar');
  }
  const content = () => page.evaluate(() => wp.data.select('core/editor').getEditedPostContent());
  // The paragraph must really be in the editor before pressing the button; insert again if not.
  for (let attempt = 0; attempt < 5 && !(await content()).includes('سلام ... عدد ٤٥٦'); attempt++) {
    await page.waitForTimeout(1000);
    await page.evaluate(() => {
      wp.data.dispatch('core/block-editor').resetBlocks([wp.blocks.createBlock('core/paragraph', { content: 'سلام ... عدد ٤٥٦' })]);
    });
  }
  await page.locator('.negaresh-fix-now').click();
  try {
    await page.waitForFunction(() => wp.data.select('core/editor').getEditedPostContent().includes('سلام… عدد ۴۵۶'), null, { timeout: 10000 });
    pass('"Fix this post now" fixes the text in the editor');
  } catch (e) {
    fail(`"Fix this post now" gave: ${await content()}`);
  }
  await page.evaluate(() => wp.data.dispatch('core/editor').undo());
  (await content()).includes('سلام ... عدد ٤٥٦') ? pass('Undo reverts the fix') : fail(`after undo: ${await content()}`);
  await page.locator('.negaresh-skip-toggle input[type="checkbox"]').check();
  (await page.locator('.negaresh-fix-now').isDisabled()) ? pass('opting out disables the button') : fail('button still enabled when opted out');
  await page.evaluate(() => wp.data.dispatch('core/editor').editPost({ title: 'browser' }));
  await page.evaluate(() => wp.data.dispatch('core/editor').savePost());
  await page.waitForFunction(() => !wp.data.select('core/editor').isSavingPost() && wp.data.select('core/editor').getCurrentPostId(), null, { timeout: 20000 });
  const saved = await page.evaluate(async () => {
    const id = wp.data.select('core/editor').getCurrentPostId();
    const post = await wp.apiFetch({ path: `/wp/v2/posts/${id}?context=edit` });
    return { skip: post.meta && post.meta._negaresh_skip, raw: post.content.raw };
  });
  saved.skip === true && saved.raw.includes('سلام ... عدد ٤٥٦')
    ? pass('opt out saved with the post, text stored as typed') : fail(`saved: ${JSON.stringify(saved)}`);
  await a11y(page, '.negaresh-skip-toggle, .negaresh-fix-now', 'editor panel');
  await page.screenshot({ path: `${shots}/editor-${process.env.SHOT_NAME || 'page'}.png` });

  // I6, B33: Tools → Negaresh, a list like the posts list; check, fix selected, fix all waiting.
  const bulkTitle = process.env.BULK_TITLE || 'bulk-target';
  const bulkUrl = `${url}/wp-admin/tools.php?page=negaresh-bulk`;
  const waitingCount = async () => Number((await page.locator('.subsubsub .waiting .count').textContent()).replace(/\D/g, ''));
  await page.goto(bulkUrl);
  const row = page.locator('#the-list tr', { has: page.locator('.row-title', { hasText: new RegExp(`^${bulkTitle}$`) }) });
  const correctRow = page.locator('#the-list tr', { hasText: `${bulkTitle}-correct-1` });
  (await row.count()) === 1 && (await correctRow.count()) === 1
    ? pass('bulk list shows the waiting posts, also those already correct (B33)') : fail('bulk list is missing waiting posts');
  (await page.locator('.displaying-num').first().textContent()).replace(/\D/g, '') === String(await waitingCount())
    ? pass('bulk list counts every waiting post (B33)') : fail('bulk list count differs from the Waiting view');
  try {
    await row.locator('.negaresh-changes details').waitFor({ timeout: 30000 });
    await row.locator('summary').click();
    (await row.locator('.negaresh-added').first().textContent())?.includes('<p>گروهی…</p>')
      ? pass('bulk list shows the changed line') : fail('bulk diff missing');
  } catch (e) {
    fail(`bulk check did not finish: ${await row.locator('.negaresh-changes').textContent()}`);
  }
  await correctRow.locator('.negaresh-no-change').waitFor({ timeout: 30000 })
    .then(() => pass('a post already correct is shown as such (B33)'), () => fail('no "already correct" note'));
  const idOf = await row.getAttribute('data-id');
  const stored = async () => page.evaluate(async (id) => (await wp.apiFetch({ path: `/wp/v2/posts/${id}?context=edit` })).content.raw, idOf);
  (await stored()) === '<p>گروهی ...</p>' ? pass('checking saved nothing') : fail(`check changed the post: ${await stored()}`);

  // Screen Options: posts per page, and pagination when there are more.
  await page.locator('#show-settings-link').click();
  await page.locator('#negaresh_bulk_per_page').fill('2');
  await Promise.all([page.waitForNavigation(), page.locator('#screen-options-apply').click()]);
  (await page.locator('#the-list tr').count()) === 2 && Number(await page.locator('.tablenav.top .total-pages').textContent()) > 1
    ? pass('bulk list is paginated by the per page screen option (B33)') : fail('bulk list pagination missing');
  await page.locator('#show-settings-link').click();
  await page.locator('#negaresh_bulk_per_page').fill('50');
  await Promise.all([page.waitForNavigation(), page.locator('#screen-options-apply').click()]);

  // Bulk action "Fix" on the selected post only.
  const before = await waitingCount();
  await row.locator('input[name="post[]"]').check();
  await page.locator('#bulk-action-selector-top').selectOption('negaresh_fix');
  page.once('dialog', (dialog) => dialog.accept());
  await Promise.all([page.waitForURL(/negaresh_fixed=1/, { timeout: 30000 }), page.locator('#doaction').click()]);
  (await stored()) === '<p>گروهی…</p>' ? pass('bulk action "Fix" fixes the selected post') : fail(`after fix: ${await stored()}`);
  (await waitingCount()) === before - 1 && (await row.count()) === 0
    ? pass('the fixed post leaves the Waiting view') : fail(`waiting ${before} → ${await waitingCount()}`);
  await page.locator('.notice-success').waitFor({ timeout: 5000 })
    .then(() => pass('a notice reports the fix'), () => fail('no notice after the fix'));

  // "Fix all waiting posts": every waiting post is checked, also those already correct (B33:
  // they used to stay waiting for ever, the count never reached zero).
  await page.goto(bulkUrl);
  page.once('dialog', (dialog) => dialog.accept());
  await Promise.all([page.waitForURL(/negaresh_checked=/, { timeout: 120000 }), page.locator('.negaresh-fix-all').click()]);
  (await waitingCount()) === 0 && (await page.locator('.negaresh-fix-all').count()) === 0
    ? pass('"Fix all waiting posts" leaves no post waiting (B33)') : fail(`still waiting: ${await waitingCount()}`);
  await page.goto(`${bulkUrl}&view=fixed`);
  (await page.locator('#the-list tr', { hasText: `${bulkTitle}-correct-2` }).count()) === 1
    ? pass('posts already correct are now in the Fixed view (B33)') : fail('already correct post not marked');
  await a11y(page, '.negaresh-bulk', 'tools page');
  await page.screenshot({ path: `${shots}/bulk-${process.env.SHOT_NAME || 'page'}.png`, fullPage: true });
  errors.length ? fail(`browser errors: ${errors.join(' | ')}`) : pass('no JavaScript errors');
} catch (e) {
  // A crash must never look like a pass: earlier checks already printed PASS.
  fail(`browser test stopped: ${e.message.split('\n')[0]}`);
} finally {
  if (browser) await browser.close();
  console.log('DONE');
}
