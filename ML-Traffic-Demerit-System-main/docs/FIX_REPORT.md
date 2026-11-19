# Emergency Fix Report — ML Traffic Demerit System

**Date:** 2026-10-17 · **Status:** ✅ All fixed and proven end-to-end — **40/40 automated UI↔DB checks pass** (see §6).

The site showed **no data and no graphs** even though the MySQL database
(`traffic_demerit`, XAMPP) was fully populated. Below: what was actually wrong,
what changed, the demo logins, how to run everything, and the proof.

---

## 1. Root cause — symptom → cause → file → fix

| # | Symptom | Root cause | File(s) | Fix |
|---|---------|-----------|---------|-----|
| 1 | **Every list/dashboard blank** | Frontend called `/api/entities/…` → **404**; Laravel serves `/api/integration/entities/…`. Fixed base URL (`VITE_API_BASE_URL=/api/integration`) plus a rewritten client with two explicit bases: ROOT `/api` (auth, CRUD, charts, system) and INTEGRATION `/api/integration` (entities, functions). |
| 2 | Dashboards showed **placeholder/stub charts** | Chart data was hardcoded in components | `src/components/charts/*`, all dashboards | New dependency-free SVG chart kit + `ChartController` — **every chart is a MySQL aggregate endpoint**, works with the ML service down |
| 3 | **Login never persisted** (0 rows in `personal_access_tokens`) | JWT-only auth stub, no Sanctum tokens | `Api/AuthController.php`, `appClient.js` | Sanctum `createToken()`; token stored; session restored on boot via `/api/auth/me`; wrong-portal creds get the **generic** error `Invalid credentials for this portal` (no role enumeration) |
| 4 | Single Login button + role dropdown | Template auth | `Home.jsx`, `Login.jsx`, `Register.jsx`, `App.jsx`, `AuthContext.jsx` | Three portal cards → `/login/driver`, `/login/officer`, `/login/admin`; portal-scoped endpoints `/api/auth/{driver|officer|admin}/login`; 5 failures → **15-min lockout (429)** + every attempt logged to `login_audits`; only drivers self-register (role coerced to `motorist`, driver profile auto-created) |
| 5 | Dashboards empty when ML service (:8001) is down | Risk scoring hard-depended on ML HTTP | `Services/RiskInferenceService.php` | `ConnectionException` → `heuristicAssessment()` (`model_version = heuristic_v1_ml_down`). HTTP *error* responses still throw → atomicity preserved |
| 6 | Admin dashboard slow / "stuck loading" | Dashboard refetched **all 15 entity lists every 10 s**, saturating PHP's single-threaded dev server | `AdminDashboard.jsx` | 30 s auto-refresh now reloads **only the 4 state-backed lists** (drivers, scores, sanctions, notifications); everything else is server-driven tables + chart endpoints |
| 7 | Admin **Charts & Analytics** tab crashed (`ReferenceError: Button is not defined`) | Missing import in the ML insights component (crashed the analytics tab *and* `/ml-insights`) | `MlInsightsPage.jsx` | `Button` import added |
| 8 | Officer violation form missing "Demo Driver"/"KDEMO 401A" | REST API caps `per_page` at 100; only page 1 fetched (402 drivers / 382 vehicles) | `OfficerDashboard.jsx` | `fetchAllPages()` walks every page of the roster for the selects |
| 9 | CRUD/charts/system requests 404'd from the rebuilt dashboards | Client sent them to `/api/integration/…`; they live under `/api/…` | `src/api/appClient.js` | `request()` gained `base: 'root'` support; all CRUD, charts, system-status, payments and void calls target `/api/…` |
| 10 | Payments could not be voided | `payments.status` enum lacked `voided`; update/destroy unsafe for money | migration + `Api/PaymentController.php` | **Create + void only** (`POST /api/payments/{id}/void` with reason); `PUT/DELETE` → 405; officer `POST /api/payments/{id}/verify` |
| 11 | No login auditing, no lockout | Nothing existed | `Api/AuthController.php` | Laravel `RateLimiter` (5 fails / 15 min, key `login-lockout:{portal}:{ip}:{email}`) → 429 via `HttpResponseException`; **all** attempts (success, failure, lockout) insert into `login_audits` |
| 12 | Editing/deleting a violation destroyed history | Rows were overwritten/deleted | `Api/ViolationController.php` | **Append-only ledger**: edit posts `violation_edit_reversal` (−old) + `violation_edit_repost` (new); delete soft-deletes the row + posts `violation_delete_reversal` (−points). `demerit_ledger_entries`, predictions and audit tables are append-only |
| 13 | ML Insights had no elbow/silhouette/PCA data | Trainer never computed/stored them | `ml_service/segmentation/train_kmeans.py` + migrations | Trainer upserts `wcss_by_k`, `silhouette_by_k`, `pca_x`, `pca_y` per driver; `/api/charts/ml-insights` serves elbow (k=1..10), silhouette, PCA scatter (400 pts), segment profiles, association rules |
| 14 | Tests could never run safely | Ran against the live DB | `phpunit.xml`, `tests/TestCase.php`, `tests/Feature/PortalApiTest.php` | Tests use `traffic_demerit_test`; the TestCase **aborts with `RuntimeException` before boot** if `DB_DATABASE` equals `traffic_demerit` |

---

## 2. Files changed (high level)

**Backend — `apps/backend/traffic-demerit-web/`**
- `routes/api.php` — portal auth routes, full REST CRUD (users, drivers, vehicles, stations, assignments, violations, appeals, payments), `/api/charts/{admin,driver,officer,ml-insights}`, `/api/system/status`
- `app/Http/Controllers/Api/` — `AuthController` (portal logins, lockout, audits, register), `ResourceController` (pagination ≤100, search, sort, filters, validation, transactions, policies), `ViolationController` (closed loop: create → ledger + balance + score + notify + sanction; 24 h edit window for officers; soft delete), `VehicleController` (owner-pinning for non-admins), `PaymentController` (create/void/verify), `ChartController` (all MySQL aggregates)
- `app/Http/Controllers/SystemStatusController.php` — DB name, row counts, ML up/down (+ latency)
- `app/Services/` — `RiskInferenceService` (ML + heuristic fallback), `NotificationService` (in-app row + email-delivery receipt row — that's why notifications appear twice per event: one `channel='in_app'`, one `channel='email'`, by design)
- 4 additive migrations (PCA columns, `wcss_by_k`/`silhouette_by_k`, soft deletes, `voided` status); **no destructive migration was run**
- `ml_service/segmentation/train_kmeans.py` — PCA n=2 + elbow/silhouette arrays, upserts
- `backup.sql` — full `mysqldump` of `traffic_demerit` taken **before any change** (2.7 MB)

**Frontend — `apps/frontend/traffic-demerit-portal/`**
- `src/api/appClient.js` — rewritten client (two API bases, portal logins, CRUD, charts, system, payments)
- `src/context/AuthContext.jsx`, `ProtectedRoute`, `App.jsx` — token-only auth, per-role routes/redirects
- `src/pages/` — `Home` (three portal cards, no dropdown), `Login`, `Register`, `AdminDashboard` (status strip, real charts, DataTables, analytics), `OfficerDashboard` (log form, tiles+charts, own-violation edits ≤24 h, payments verify), `DriverDashboard` (gauge, points-over-time, ledger, vehicles, sanctions, notifications, M-Pesa flow, appeals), `MlInsightsPage`
- `src/components/` — `AppLayout` (per-role nav + ML Insights + `SystemStatusFooter`), `SystemStatusFooter` (DB name / ML badge / row counts), `charts/*` (SVG kit), `crud/DataTable.jsx` (server-driven pagination/search/sort/filters, modal CRUD, soft-delete confirm)
- `.env` + `.env.example` — `VITE_API_BASE_URL=/api/integration` (was `/api`, the #1 root cause)

**Docs / proof**
- `docs/screenshots/` — 19 screenshots (`01-landing` … `19-driver-appeals`) + `proof-results.json`
- `docs/proof/proof.mjs` — the Playwright harness (see §6)
- `docs/FIX_REPORT.md` — this file

---

## 3. Demo logins

| Portal | URL | Email | Password | Lands on |
|--------|-----|-------|----------|----------|
| Driver | `http://localhost:5173/login/driver` | `driver@ntsa.go.ke` | `Driver@123` | `/driver` |
| Officer | `http://localhost:5173/login/officer` | `officer@ntsa.go.ke` | `Officer@123` | `/officer` |
| Admin | `http://localhost:5173/login/admin` | `admin@ntsa.go.ke` | `Admin@123` | `/admin` |

- Passwords were reset with `php artisan demo:set-passwords` (already run).
- Only the **Driver** portal has a Register link (self-registration → `motorist` + driver profile).
- Using the wrong portal's credentials returns the generic
  `Invalid credentials for this portal`; 5 failures within 15 minutes →
  **429 “Too many failed attempts. Access locked for 15 minutes.”** (logged to `login_audits`).

---

## 4. How to start everything

> Note: the project is **double-nested** — use the inner folder
> `ML-Traffic-Demerit-System-main\ML-Traffic-Demerit-System-main` (or absolute paths).

1. **XAMPP** — start **MySQL** (Apache is not needed; the backend has its own server on :8000).
2. **Backend** (terminal 1):
   ```powershell
   cd C:\Users\chimo\Downloads\ML-Traffic-Demerit-System-main\ML-Traffic-Demerit-System-main\apps\backend\traffic-demerit-web
   C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000
   ```
3. **Frontend** (terminal 2):
   ```powershell
   cd C:\Users\chimo\Downloads\ML-Traffic-Demerit-System-main\ML-Traffic-Demerit-System-main\apps\frontend\traffic-demerit-portal
   npm install        # first run only
   npm run dev        # http://localhost:5173
   ```
4. **ML service (optional)** — the whole system works without it (heuristic fallback + MySQL charts):
   ```powershell
   cd ...\apps\backend\traffic-demerit-web\ml_service
   pip install -r requirements.txt     # first run only
   python -m uvicorn app:app --port 8001
   ```
   To refresh ML Insights artifacts: `C:\xampp\php\php.exe` is irrelevant here — run
   `python segmentation\train_kmeans.py` inside `ml_service\` (upserts PCA/elbow/silhouette into MySQL).
5. **Tests** (uses `traffic_demerit_test`; refuses to run against the live DB):
   ```powershell
   cd ...\apps\backend\traffic-demerit-web
   C:\xampp\php\php.exe artisan test
   ```

The PHP dev server is **single-threaded** — keep extra browser tabs of the app
closed while demoing; it is the main cause of slow page loads in this setup.

---

## 5. What each portal now does

- **Admin** — status strip (`Database: traffic_demerit`, ML badge, live row counts); DataTables for violations/payments/drivers with server-side pagination, search, sort, filters; **Charts & Analytics** (risk distribution, monthly violations & fines, by offence type/station, payments summary, sanctions/appeals by status, watch list, leaderboard); soft-delete violations with confirmation; ML Insights.
- **Officer** — log-a-violation form (driver/vehicle/offence/zone/weather/location/evidence; speed vs limit) that posts the full closed loop; overview tiles + own-issued trend chart; **Recent Violations** (own rows, editable within 24 h); **Payments** tab with verify.
- **Driver** — demerit gauge, points-over-time, **ledger** (append-only), vehicles, sanctions, notifications, outstanding violations with **M-Pesa initiate → officer/admin verify** flow, appeal submission.
- **ML Insights** (`/ml-insights`, admin) — elbow curve, silhouette-vs-k, PCA scatter of 400 drivers, 3 segment profiles, association rules + bubble chart — all from MySQL columns written by the trainer, so they render even while the ML service is down.

---

## 6. Proof

### Automated end-to-end proof — **40/40 checks passed**
`docs/proof/proof.mjs` (Playwright + Chromium, no npm deps for SQL — uses `mysql.exe`).
Every UI count is compared against live SQL in `traffic_demerit`.

Key results from the final run:

```
admin status strip users=412 drivers=402 vehicles=382 violations=1500 payments=1167 appeals=19  ← all UI==SQL
officer "Violations Issued" tile = SQL (148)
officer logs violation → toast "+4 points · balance 4 · risk LOW (15%)"
  → MySQL: violation 1506 speeding/4pts/officer 60/driver 401
  → ledger +4 (running balance 4), in-app notification, heuristic risk row
driver sees: TOTAL VIOLATIONS 1 · Current Balance 4 · ledger entry
admin sees it (+4) → officer edits → ledger "4/auto_post, -4/edit_reversal, 8/edit_repost"
  → balance 8 → admin deletes → soft-delete + "-8/delete_reversal" → balance 0 → gone from UI
ML Insights: elbow ✓ silhouette ✓ PCA scatter ✓ segment profiles ✓ association rules ✓
```

Screenshots of every dashboard/chart: `docs/screenshots/01-landing.png` … `19-driver-appeals.png`
(+ `proof-results.json` with the full check list).

**Re-run it** (with all three services up):
```powershell
cd C:\Users\chimo\AppData\Local\Temp\opencode\pw-proof   # or docs\proof after npm i playwright
node proof.mjs
```
It self-cleans: each run creates → edits → deletes its own violation, restoring the
demo driver's balance to 0. `docs/proof/proof.mjs` assumes this machine's XAMPP
paths — adjust the `MYSQL`/`SHOTS` constants elsewhere.

### Backend test suite
`php artisan test` → **43 tests, 250 assertions, green** on `traffic_demerit_test`
(portal logins + generic mismatch error + lockout/429 + `login_audits` rows + full
CRUD/policy matrix + closed-loop atomicity + soft delete + payments create/void only
+ chart aggregates + ML-down fallback). The live-DB abort guard is included and verified.

---

## 7. Design notes (intentional, not bugs)

- **Notifications appear twice per event** — one `channel='in_app'` row (what the UI shows) and one `channel='email'` delivery receipt. The in-app count is always exactly 1.
- **Ledger is append-only** — edits/deletes append reversal + repost rows; nothing is ever overwritten or hard-deleted.
- **Payments: create + void only** — money rows are never edited; officer verifies.
- **Officer edit window is 24 h**; admins may edit/delete any violation.
- **Non-admins cannot reassign vehicle owners** (create pins to own account; update ignores `user_id`).
- Current demo data after the proof run: users 412 · drivers 402 · vehicles 382 ·
  violations 1500 live (+ soft-deleted proof rows kept as audit evidence) · payments 1167 · appeals 19 ·
  demo driver balance **0** (proof restored it).
