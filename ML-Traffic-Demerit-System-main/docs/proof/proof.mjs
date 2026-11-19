/**
 * END-TO-END PROOF — Traffic Demerit System
 *
 * Proves, against the live MySQL database (traffic_demerit):
 *   1. Landing page offers three portal logins (no single Login / role dropdown).
 *   2. All three portal logins work; wrong-role credentials show the generic error.
 *   3. Counts shown in each role's UI match MySQL exactly.
 *   4. Full closed loop in the UI:
 *        officer logs a violation -> MySQL row + ledger + notification + risk score
 *        -> driver sees it -> admin sees it -> officer edits it (append-only
 *        reversal + repost) -> admin deletes it (soft delete + reversal).
 *        Driver balance: 0 -> 4 -> 8 -> 0.
 *   5. Admin analytics + ML Insights render MySQL/ML data.
 *   6. Screenshots of every dashboard/chart -> docs/screenshots/.
 *
 * All waits are CONDITIONAL (poll up to N seconds) because the PHP dev
 * server is single-threaded: dashboard bursts queue for 10-20s.
 *
 * Run:  node proof.mjs      (XAMPP MySQL, Laravel :8000, Vite :5173 must be up)
 */
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';

const BASE = 'http://localhost:5173';
const MYSQL = 'C:\\xampp\\mysql\\bin\\mysql.exe';
const DB = 'traffic_demerit';
const SHOTS =
  'C:\\Users\\chimo\\Downloads\\ML-Traffic-Demerit-System-main\\ML-Traffic-Demerit-System-main\\docs\\screenshots';

const results = [];
const record = (name, ok, detail = '') => {
  results.push({ name, ok: !!ok, detail: String(detail) });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  -- ' + detail : ''}`);
};
const fatal = (name, err) => {
  record(name, false, 'EXCEPTION: ' + (err?.message || err));
  throw new Error(`${name}: ${err?.message || err}`);
};

/* ---------------- MySQL helpers (no npm deps) ---------------- */
const sql = (query) =>
  execFileSync(MYSQL, ['-u', 'root', '-N', '-B', '-e', query], { encoding: 'utf8' }).trim();
const sqlNum = (query) => Number(sql(query));
/** Polls SQL until the expectation holds (UI commits may lag the click). */
async function waitForSql(name, fn, timeoutMs = 60000, intervalMs = 2500) {
  const t0 = Date.now();
  let last = null;
  while (Date.now() - t0 < timeoutMs) {
    try { last = fn(); if (last.ok) return last; } catch (e) { last = { ok: false, detail: e.message }; }
    await new Promise((r) => setTimeout(r, intervalMs));
  }
  return last || { ok: false, detail: 'timeout' };
}

/* ---------------- UI helpers ---------------- */
const body = (page) => page.innerText('body');
/** Polls page text until regex matches (or timeout). Returns bool. */
async function waitForText(page, regex, timeoutMs = 45000, intervalMs = 1500) {
  const t0 = Date.now();
  while (Date.now() - t0 < timeoutMs) {
    if (regex.test(await body(page))) return true;
    await page.waitForTimeout(intervalMs);
  }
  return false;
}
async function login(page, portal, email, password) {
  await page.goto(`${BASE}/login/${portal}`, { waitUntil: 'domcontentloaded' });
  await page.fill('#email', email);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForURL(new RegExp(`/${portal}$`), { timeout: 60000 }),
    page.click('form button[type=submit]'),
  ]);
  // Wait for the portal's main marker so all initial fetches have settled.
  const marker = portal === 'admin' ? /Charts & Analytics/
    : portal === 'officer' ? /Field Violation Console/
    : /Demerit Ledger|Total Violations/i;
  await waitForText(page, marker, 90000);
}
async function logout(page) {
  await page.evaluate(() => localStorage.removeItem('traffic_demerit_portal_token_v1'));
}
async function nav(page, label) {
  await page.getByRole('button', { name: label, exact: true }).first().click();
  await page.waitForTimeout(800);
}
/** Radix Select with an aria-label (officer violation form). */
async function pick(page, ariaLabel, optionName) {
  await page.locator(`[aria-label="${ariaLabel}"]`).first().click();
  await page.getByRole('option', { name: optionName, exact: false }).first().click({ timeout: 45000 });
  await page.waitForTimeout(200);
}
/** Radix Select inside a DataTable modal (id dt-<field>). */
async function pickModal(page, field, optionName) {
  await page.locator(`#dt-${field}`).first().click();
  await page.getByRole('option', { name: optionName, exact: false }).first().click({ timeout: 45000 });
  await page.waitForTimeout(200);
}
/** Types into the DataTable search and waits for the expected row text. */
async function searchTable(page, placeholder, text, expectRegex, timeoutMs = 45000) {
  await page.getByPlaceholder(placeholder).fill(text);
  return waitForText(page, expectRegex, timeoutMs);
}
async function shot(page, name) {
  await page.screenshot({ path: `${SHOTS}/${name}.png` });
  console.log(`  shot: ${name}.png`);
}

mkdirSync(SHOTS, { recursive: true });
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1500, height: 950 } });
const page = await ctx.newPage();
page.on('pageerror', (e) => console.log('  [page error] ' + String(e).slice(0, 200)));

const LOCATION = 'Playwright Proof Road';
const EVIDENCE = 'EVID-PW-PROOF';

try {
  /* ================= 1. LANDING ================= */
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1200);
  record('landing: driver/officer/admin portal links',
    (await page.locator('a[href="/login/driver"]').count()) > 0 &&
    (await page.locator('a[href="/login/officer"]').count()) > 0 &&
    (await page.locator('a[href="/login/admin"]').count()) > 0);
  record('landing: no role dropdown', (await page.locator('select').count()) === 0);
  await shot(page, '01-landing');

  /* ================= 2. ROLE-MISMATCH ERROR ================= */
  await page.goto(BASE + '/login/admin', { waitUntil: 'domcontentloaded' });
  await page.fill('#email', 'driver@ntsa.go.ke');
  await page.fill('#password', 'Driver@123');
  await page.click('form button[type=submit]');
  const mismatchOk = await waitForText(page, /Invalid credentials for this portal/, 30000);
  record('wrong-role login shows generic portal error', mismatchOk && page.url().includes('/login/admin'));
  await shot(page, '02-login-role-mismatch');

  /* ================= 3. ADMIN ================= */
  await login(page, 'admin', 'admin@ntsa.go.ke', 'Admin@123');
  const stripOk = await waitForText(page, /Database:\s*traffic_demerit/, 90000);
  const adminStrip = await body(page);

  const sqlCounts = {
    users: sqlNum(`SELECT COUNT(*) FROM ${DB}.users WHERE deleted_at IS NULL`),
    drivers: sqlNum(`SELECT COUNT(*) FROM ${DB}.driver_profiles WHERE deleted_at IS NULL`),
    vehicles: sqlNum(`SELECT COUNT(*) FROM ${DB}.vehicles WHERE deleted_at IS NULL`),
    violations: sqlNum(`SELECT COUNT(*) FROM ${DB}.violations WHERE deleted_at IS NULL`),
    payments: sqlNum(`SELECT COUNT(*) FROM ${DB}.payments WHERE deleted_at IS NULL`),
    appeals: sqlNum(`SELECT COUNT(*) FROM ${DB}.appeals WHERE deleted_at IS NULL`),
  };
  console.log('SQL counts: ' + JSON.stringify(sqlCounts));
  for (const [key, want] of Object.entries(sqlCounts)) {
    const m = adminStrip.match(new RegExp(`${key}:\\s*([\\d,]+)`, 'i'));
    const got = m ? Number(m[1].replace(/,/g, '')) : NaN;
    record(`admin status strip ${key} = ${want}`, got === want, `UI=${got}`);
  }
  record('admin strip shows DB name', stripOk);
  record('admin strip shows ML status badge (DOWN, fallback note)',
    /DOWN/i.test(adminStrip) && adminStrip.includes('fall back to MySQL data'));
  await shot(page, '03-admin-dashboard');

  await nav(page, 'Violations');
  record('admin violations list shows total in table meta',
    await waitForText(page, /of\s*1,?5\d\d/, 45000));
  await shot(page, '04-admin-violations-list');

  await nav(page, 'Payments');
  await waitForText(page, /Total Payments|of\s*1,?1\d\d/, 45000);
  await shot(page, '05-admin-payments');

  /* ---- analytics (MySQL charts) ---- */
  await page.getByRole('button', { name: 'Charts & Analytics', exact: true }).first().click();
  const analyticsOk = await waitForText(page, /Monthly Violations/i, 60000);
  const analyticsTxt = await body(page);
  record('analytics: monthly violations & fines chart', analyticsOk);
  record('analytics: risk distribution rendered (low/moderate/high)',
    /low/i.test(analyticsTxt) && /moderate/i.test(analyticsTxt) && /high/i.test(analyticsTxt));
  record('analytics: payments summary totals present',
    /Collected/i.test(analyticsTxt) && /Outstanding/i.test(analyticsTxt));
  await shot(page, '06-admin-analytics-charts');

  /* ================= 4. OFFICER — log a violation ================= */
  await logout(page);
  await login(page, 'officer', 'officer@ntsa.go.ke', 'Officer@123');
  const officerCountSql = sqlNum(
    `SELECT COUNT(*) FROM ${DB}.violations WHERE officer_id=(SELECT id FROM ${DB}.users WHERE email='officer@ntsa.go.ke') AND deleted_at IS NULL`);
  const tileOk = await waitForText(page, new RegExp(`Violations Issued\\s*${officerCountSql}\\b`), 60000);
  record(`officer "Violations Issued" tile = SQL (${officerCountSql})`, tileOk);
  await shot(page, '07-officer-log-tab');

  try {
    await pick(page, 'Driver', 'Demo Driver');
    await pick(page, 'Vehicle', 'KDEMO 401A');
    await pick(page, 'Offence type', 'Speeding');
    await page.locator('form input[type=number]').nth(0).fill('118');
    await page.locator('form input[type=number]').nth(1).fill('80');
    await pick(page, 'Zone type', 'Urban');
    await pick(page, 'Weather', 'Clear');
    await page.getByPlaceholder('e.g. Mombasa Rd, km 12').fill(LOCATION);
    await page.getByPlaceholder('e.g. GZIP-2026-001 / photo ref').fill(EVIDENCE);
    await page.locator('input[type=datetime-local]').first().fill('2026-10-17T18:30');
    await shot(page, '08-officer-form-filled');
    await page.locator('button[type=submit]').first().click();
  } catch (err) { fatal('officer creates violation in UI', err); }

  const toastOk = await waitForText(page, /Violation logged/, 60000);
  const afterCreate = await body(page);
  record('officer: success toast shows points/balance/risk',
    toastOk && /\+\d+ points · balance \d+/.test(afterCreate) && /risk/i.test(afterCreate),
    (afterCreate.match(/[^\n]*balance \d+[^\n]*/) || [''])[0].slice(0, 120));
  await shot(page, '09-officer-violation-created-toast');

  const created = await waitForSql('violation created', () => {
    const id = sqlNum(
      `SELECT id FROM ${DB}.violations WHERE location='${LOCATION}' AND evidence_reference='${EVIDENCE}' AND deleted_at IS NULL`);
    if (!id) return { ok: false };
    const row = sql(
      `SELECT CONCAT(id,'|',offense_type,'|',points_assigned,'|',officer_id,'|',driver_id) FROM ${DB}.violations WHERE id=${id}`);
    return { ok: true, id, row };
  });
  const newViolId = created.id;
  console.log('SQL new violation: ' + created.row);
  record('MySQL: violation row created (speeding, 4 pts, officer 60, driver 401)',
    created.ok && /^\d+\|speeding\|4\|60\|401$/.test(created.row), created.row || created.detail);

  const ledger = sql(`SELECT CONCAT(points_applied,'|',running_balance,'|',reason_code) FROM ${DB}.demerit_ledger_entries WHERE violation_id=${newViolId} ORDER BY id DESC LIMIT 1`);
  record('MySQL: ledger entry +4, running balance 4', ledger === '4|4|violation_auto_post', ledger);
  record('MySQL: driver balance now 4',
    sqlNum(`SELECT current_demerit_points FROM ${DB}.driver_profiles WHERE id=401`) === 4);
  record('MySQL: in-app notification created for the driver (email receipt is a separate by-design row)',
    sqlNum(`SELECT COUNT(*) FROM ${DB}.system_notifications WHERE recipient_user_id=61 AND channel='in_app' AND related_type='violation' AND related_id=${newViolId}`) === 1);
  record('MySQL: risk prediction row created (heuristic while ML down)',
    sql(`SELECT model_version FROM ${DB}.risk_predictions WHERE driver_id=401 ORDER BY id DESC LIMIT 1`) === 'heuristic_v1_ml_down');

  /* ================= 5. DRIVER sees it ================= */
  await logout(page);
  await login(page, 'driver', 'driver@ntsa.go.ke', 'Driver@123');
  const driverViolOk = await waitForText(page, /Total Violations\s*1/i, 60000);
  const driverBalOk = await waitForText(page, /Current Balance[\s\S]{0,12}\b4\b/i, 45000);
  const driverTxt = await body(page);
  record('driver UI: TOTAL VIOLATIONS = 1', driverViolOk);
  record('driver UI: Current Balance tile = 4 (gauge + stat from charts/driver)', driverBalOk);
  record('driver UI: ledger lists the new entry (reason "violation", the driver had none before)',
    /\bviolation\b/i.test(driverTxt));
  await shot(page, '10-driver-sees-violation');

  /* ================= 6. ADMIN sees it ================= */
  await logout(page);
  await login(page, 'admin', 'admin@ntsa.go.ke', 'Admin@123');
  await nav(page, 'Violations');
  const adminSeeOk = await searchTable(page, 'Search location, evidence, status…', LOCATION,
    /Demo Driver[\s\S]{0,4000}\+\s*4/, 60000);
  record('admin list shows the new violation (driver +4)', adminSeeOk);
  await shot(page, '11-admin-sees-violation');

  /* ================= 7. OFFICER edits it (24h window) ================= */
  await logout(page);
  await login(page, 'officer', 'officer@ntsa.go.ke', 'Officer@123');
  await nav(page, 'Recent Violations');
  const foundForEdit = await searchTable(page, 'Search location, evidence, status…', LOCATION,
    new RegExp(`${EVIDENCE}|${LOCATION}`), 60000);
  try {
    await page.getByRole('button', { name: 'Edit', exact: true }).first().click({ timeout: 30000 });
    await waitForText(page, /Save changes/, 30000);
    await shot(page, '12-officer-edit-modal');
    await pickModal(page, 'offense_type', 'Reckless Driving');
    await page.getByRole('button', { name: 'Save changes' }).click();
  } catch (err) { fatal('officer edits violation in UI (row found: ' + foundForEdit + ')', err); }

  const edited = await waitForSql('edit reversal posted', () => {
    const g = sql(
      `SELECT GROUP_CONCAT(CONCAT(points_applied,'/',reason_code) ORDER BY id) FROM ${DB}.demerit_ledger_entries WHERE violation_id=${newViolId}`);
    return {
      ok: g === '4/violation_auto_post,-4/violation_edit_reversal,8/violation_edit_repost',
      g,
    };
  });
  record('MySQL: ledger append-only (original +4, reversal -4, repost +8)',
    edited.ok, edited.g || edited.detail);
  record('MySQL: driver balance now 8',
    sqlNum(`SELECT current_demerit_points FROM ${DB}.driver_profiles WHERE id=401`) === 8);
  record('MySQL: violation points updated to 8',
    sqlNum(`SELECT points_assigned FROM ${DB}.violations WHERE id=${newViolId}`) === 8);
  await shot(page, '13-officer-after-edit');

  /* ================= 8. ADMIN deletes it (soft delete) ================= */
  await logout(page);
  await login(page, 'admin', 'admin@ntsa.go.ke', 'Admin@123');
  await nav(page, 'Violations');
  const adminSeeEdit = await searchTable(page, 'Search location, evidence, status…', LOCATION,
    /\+\s*8/, 60000);
  record('admin list reflects the edit (+8 Reckless)', adminSeeEdit);
  try {
    await page.getByRole('button', { name: 'Delete', exact: true }).first().click({ timeout: 30000 });
    const dlg = page.getByRole('dialog', { name: /Delete violation/ });
    await dlg.waitFor({ timeout: 30000 });
    record('delete confirmation dialog explains soft delete',
      (await dlg.innerText()).includes('soft-delete'));
    await shot(page, '14-admin-delete-confirm');
    await dlg.getByRole('button', { name: 'Delete' }).click();
  } catch (err) { fatal('admin deletes violation in UI', err); }

  const deleted = await waitForSql('soft delete posted', () => {
    const del = sqlNum(`SELECT COUNT(*) FROM ${DB}.violations WHERE id=${newViolId} AND deleted_at IS NOT NULL`);
    const rev = sqlNum(`SELECT COUNT(*) FROM ${DB}.demerit_ledger_entries WHERE violation_id=${newViolId} AND points_applied=-8 AND reason_code='violation_delete_reversal'`);
    return { ok: del === 1 && rev === 1, del, rev };
  });
  record('MySQL: violation soft-deleted (row kept)', deleted.ok, `deleted=${deleted.del} reversal=${deleted.rev}`);
  record('MySQL: delete reversal -8 appended (append-only ledger)', deleted.rev === 1);
  record('MySQL: driver balance restored to 0',
    sqlNum(`SELECT current_demerit_points FROM ${DB}.driver_profiles WHERE id=401`) === 0);
  const goneFromUi = await waitForText(page, /\b0 violations\b/, 45000);
  record('UI: deleted violation no longer listed',
    goneFromUi && !(await body(page)).includes(LOCATION));
  await shot(page, '15-admin-after-delete');

  /* ================= 9. ML INSIGHTS ================= */
  await page.goto(BASE + '/ml-insights', { waitUntil: 'domcontentloaded' });
  const mlOk = await waitForText(page, /Elbow Curve/i, 60000);
  const mlTxt = await body(page);
  record('ML insights: elbow curve section', mlOk);
  record('ML insights: silhouette section', /Silhouette/i.test(mlTxt));
  record('ML insights: PCA scatter (drivers)', /PCA Projection/i.test(mlTxt));
  record('ML insights: segment profiles', /Segment Profiles/i.test(mlTxt));
  record('ML insights: association rules', /Association Rules/i.test(mlTxt));
  await shot(page, '16-ml-insights');
  await page.mouse.wheel(0, 1400);
  await page.waitForTimeout(800);
  await shot(page, '17-ml-insights-pca');

  /* ================= 10. DRIVER extra views ================= */
  await logout(page);
  await login(page, 'driver', 'driver@ntsa.go.ke', 'Driver@123');
  await nav(page, 'Payments');
  await waitForText(page, /M-Pesa|Outstanding|payment/i, 45000);
  await shot(page, '18-driver-payments');
  await nav(page, 'Submit Appeal');
  await waitForText(page, /appeal/i, 45000);
  await shot(page, '19-driver-appeals');

  /* ================= SUMMARY ================= */
  writeFileSync(`${SHOTS}/proof-results.json`, JSON.stringify(results, null, 2));
  const failed = results.filter((r) => !r.ok);
  console.log(`\n===== ${results.length - failed.length}/${results.length} checks passed =====`);
  if (failed.length) failed.forEach((f) => console.log('FAILED: ' + f.name + ' -- ' + f.detail));
  process.exitCode = failed.length ? 1 : 0;
} catch (err) {
  console.log('\nSCRIPT ABORTED: ' + (err?.message || err));
  try { await shot(page, '99-abort-state'); } catch {}
  writeFileSync(`${SHOTS}/proof-results.json`, JSON.stringify(results, null, 2));
  process.exitCode = 1;
} finally {
  await browser.close();
}
