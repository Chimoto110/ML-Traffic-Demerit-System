# User Manual — ML Traffic Demerit System

**Version:** 2026-10-17 · Companion docs: [PROJECT_OVERVIEW.md](./PROJECT_OVERVIEW.md) (architecture & ML), [FIX_REPORT.md](./FIX_REPORT.md) (technical fixes)

---

## 1. What the system does

The system manages **traffic demerit points** for drivers:

- **Officers** record violations (speeding, reckless driving, no-seat-belt…). Every violation automatically assigns **demerit points** to the driver's licence, updates their balance, scores their **risk**, evaluates **sanctions**, and **notifies** the driver — in one transaction.
- **Drivers** see their points, history (ledger), sanctions and fines; they can **pay fines (M-Pesa)** and **appeal** violations.
- **Administrators** oversee everything: all data tables, charts & analytics, sanctions, payments, appeals, audit trail — plus an **ML Insights** page (driver segmentation, risk model diagnostics, association rules).

The **ML service** (Python) scores every driver's reoffending risk and powers the Insights page — but the whole portal keeps working even when it is offline (it falls back to a deterministic heuristic and MySQL-stored ML results).

---

## 2. System structure

### 2.1 The four parts

```
Browser  ──►  React portal (:5173)  ──►  Laravel API (:8000)  ──►  MySQL `traffic_demerit`
                                                     │
                                                     └──►  ML service (:8001, optional)
```

| Part | What it is | How a user experiences it |
|------|-----------|---------------------------|
| **Portal** | React single-page app | The website at `http://localhost:5173` |
| **API** | Laravel REST API | Invisible; all screens load/save through it |
| **MySQL** | Database `traffic_demerit` | The source of truth — every number on screen is a live SQL count |
| **ML service** | FastAPI + scikit-learn | The **ML: UP/DOWN** badge and risk scores on the admin/officer screens |

### 2.2 The three portals (+1 admin page)

| Portal | URL | Who | Landing page |
|--------|-----|-----|--------------|
| **Driver** | `/login/driver` | Motorists | `/driver` |
| **Officer** | `/login/officer` | Traffic officers | `/officer` |
| **Admin** | `/login/admin` | NTSA administrators | `/admin` |
| **ML Insights** | `/ml-insights` | Admins only | — |

The home page (`/`) shows **three portal cards** — there is no single "Login" button and no role dropdown; each portal has its own login page and its own API endpoint.

### 2.3 Navigation map (sidebar, left)

**Driver portal** — Demerit Ledger · Sanctions · Notifications · Payments · Submit Appeal
**Officer portal** — Log Violation · Recent Violations · Payments
**Admin portal** — Drivers · Violations · Sanctions · Payments · Notifications · Appeals · Operations · Audit Trail · Compliance Reports — plus two header buttons: **Charts & Analytics** and **ML Insights →**. The admin screens also carry a live **status strip** (database name, row counts, ML up/down badge).

### 2.4 Key data objects

`users` (roles: motorist / officer / admin) → `driver_profiles` (points balance, status) → `vehicles` (owned by drivers) · `violations` (an officer's record against a driver) → `demerit_ledger_entries` (**append-only** points history) · `risk_predictions` (ML/heuristic score per assessment) · `sanctions` · `appeals` · `payments` (fines, M-Pesa) · `system_notifications` · `login_audits` · ML tables (`ml_driver_features`, `driver_segments`, `segment_models`, `association_rules`).

---

## 3. Getting started

1. Start **MySQL** (XAMPP Control Panel), then the backend (`artisan serve :8000`) and the frontend (`npm run dev :5173`). Optionally the ML service (`uvicorn app:app --port 8001`).
2. Open `http://localhost:5173` and pick your portal card.

| Portal | Email | Password |
|--------|-------|----------|
| Driver | `driver@ntsa.go.ke` | `Driver@123` |
| Officer | `officer@ntsa.go.ke` | `Officer@123` |
| Admin | `admin@ntsa.go.ke` | `Admin@123` |

- **Only drivers can self-register** ("Register" link on the driver login). Registration creates the login **and** the driver profile automatically.
- Using the wrong portal's credentials shows the generic error
  **"Invalid credentials for this portal"** (the system never reveals which roles exist).
- After **5 failed attempts** within 15 minutes the account/IP is **locked for 15 minutes** (HTTP 429: *"Too many failed attempts. Access locked for 15 minutes."*). All attempts — successful or not — are written to the `login_audits` table.
- To switch accounts, log out (top-right). Tokens are stored per browser; closing the browser and returning to the site restores your session automatically.

---

## 4. Processes (how work flows through the system)

### 4.1 Officer — logging a violation (the core process)

1. Sidebar → **Log Violation**.
2. Fill the form:
   - **Driver** and **Vehicle** (drop-downs listing the full national roster),
   - **Offence type** (e.g. Speeding, Reckless Driving, No Seat Belt…),
   - **Speed recorded** (km/h) vs **Speed limit** (km/h) — for speeding offences,
   - **Zone type** (Urban / School zone / Highway), **Weather** (Clear / Rain / Fog…),
   - **Location** (e.g. *"Mombasa Rd, km 12"*), **Evidence reference** (e.g. *"GZIP-2026-001 / photo ref"*), **Date & time**.
3. Click **Log violation**. In one atomic database transaction the system:
   1. creates the violation,
   2. assigns **demerit points** (based on offence severity — e.g. speeding 4 pts, reckless driving 8 pts),
   3. appends a **ledger entry** with the new running balance,
   4. updates the driver's **balance** and account status,
   5. **scores the driver's risk** (ML service, or heuristic if it's down),
   6. **evaluates sanctions** (e.g. suspension when thresholds are crossed),
   7. **notifies** the driver.
4. A green toast confirms the closed loop, e.g.
   **"Violation logged — +4 points · balance 4 · risk LOW (15%)"**.
5. The driver immediately sees it in their portal; the admin sees it under **Violations**.

### 4.2 Officer — correcting a violation (24-hour window)

Sidebar → **Recent Violations** → search (location, evidence, status) → **Edit** on your own violation — allowed only within **24 hours** of logging it.
Corrections are **append-only**: the system posts a *reversal* entry (−old points) and a *repost* entry (new points). Nothing is overwritten, so the audit history always shows what happened. Editing cannot change the driver or the officer.

### 4.3 Admin — deleting a violation

**Violations** → search → **Delete** → confirmation dialog (it explains the row is **soft-deleted**, not destroyed) → **Delete**.
The violation disappears from all lists and reports, its points are reversed via a `violation_delete_reversal` ledger entry (the driver's balance is corrected), and the row itself is retained for audit.

### 4.4 Driver — paying a fine (M-Pesa)

1. Sidebar → **Payments**: see your violations with outstanding fines.
2. Click the **M-Pesa** pay action on a fine → a payment record is created (status *pending*, awaiting verification).
3. An **officer** (Payments tab → **Verify**) or **admin** verifies the payment.
4. The fine is marked settled and disappears from *Outstanding*; the payment appears in the admin's payments summary (Collected / Invoiced / Outstanding).
5. Admins can **void** a payment with a reason (payments are never edited or deleted).

### 4.5 Driver — appealing a violation

Sidebar → **Submit Appeal** → pick the violation, write the reason → submit.
- The appeal appears in the admin's **Appeals** queue (status *pending*), and admins are notified.
- The admin **approves or rejects** with a decision note; the decision and the driver's notification are recorded.
- Appeal status is visible to the driver at all times (pending / granted / rejected).

### 4.6 Sanctions (automatic)

Whenever a driver is scored (i.e., after any of their violations), the system evaluates whether their balance/risk crosses a **sanction threshold** (e.g. suspension). If so, a sanction is created automatically and both the driver (Notifications) and the admin (Sanctions tab) see it. Admins can **lift** a sanction from the Sanctions tab.

### 4.7 Admin — Charts & Analytics

Header button **Charts & Analytics** (all MySQL-backed, works with the ML service down):

- **Driver Risk Distribution** — low / moderate / high counts (latest prediction per driver),
- **Monthly Violations & Fines** — trend line/bars,
- **Violations by Offence Type** and **by Station**,
- **Payments Summary** — totals: payments, collected, invoiced, outstanding,
- **Sanctions & Appeals by Status**,
- **Live Demerit Watch** — highest-balance drivers with colour-coded gauges (auto-refreshes every 30 s),
- **Leaderboard** — officers by violations issued.

The **status strip** at the bottom of every admin screen shows: `Database: traffic_demerit (connected)`, live row counts (users, drivers, vehicles, violations, payments, appeals) and the **ML badge** (`UP` with latency, or `DOWN` + *"dashboards fall back to MySQL data"*).

### 4.8 Admin — ML Insights (`/ml-insights`)

| Section | What it shows |
|---------|---------------|
| **Elbow Curve** | Within-cluster variation (WCSS) for k = 1…10 — how 3 driver segments were chosen |
| **Silhouette** | Cluster-quality score for k = 2…10 (peak confirms k = 3) |
| **PCA Projection** | Scatter plot of **all 400 drivers**, coloured by segment (2-D behavioural projection) |
| **Segment Profiles** | The 3 segments with average violation count, demerit points, speed-over-limit, unpaid-fine ratio, fines (KES), night-time share |
| **Association Rules** | Top rules mined from violation data (support, confidence, coverage, lift) + a bubble chart (support × confidence, bubble size = lift) |

Everything on this page comes from MySQL columns written by the ML training step, so it renders **even while the ML service is offline**. A **Refresh** button re-fetches.

### 4.9 Officer — dashboards & tools

- **Overview**: own stats — *Violations Issued* (your total), charts of your violations over time, and the accident-risk predictor (run it against the ML service for an on-demand risk read-out).
- **Recent Violations**: your own issued violations with search/sort; edit within the 24 h window (§4.2).
- **Payments**: verify fines drivers have paid via M-Pesa (§4.4).

### 4.10 Driver — dashboard & tools

- **Overview**: **demerit gauge** (balance, colour-coded by risk), *Current Balance*, *Risk Classification*, *Total Violations*, *Profile Status* tiles, and a **points-over-time** chart.
- **Demerit Ledger**: the complete, append-only history of every points change (reason, points applied, running balance) + **My Vehicles** (add/edit your own vehicles; you cannot reassign a vehicle to someone else).
- **Sanctions**: any sanctions on your licence. **Notifications**: system messages (new violations, appeal outcomes…).
- **Payments**: outstanding fines + M-Pesa flow (§4.4). **Submit Appeal**: appeal a violation (§4.5).

---

## 5. Roles & permissions

| Capability | Driver | Officer | Admin |
|-----------|:------:|:-------:|:-----:|
| View own profile / ledger / sanctions / notifications | ✅ | — | — |
| Register (self-service) | ✅ | — | — |
| Create / edit own vehicles (owner-pinned) | ✅ | — | ✅ |
| Log violations | ❌ | ✅ | ✅ |
| Edit a violation | own only ❌ (no access) | **own, ≤ 24 h** | ✅ any |
| Delete (soft) a violation | ❌ | ❌ (403) | ✅ |
| Initiate M-Pesa payment | ✅ | — | — |
| Verify / void payments | ❌ | verify ✅ | ✅ both |
| Submit / review appeals | submit ✅ | ❌ | review ✅ |
| Lift sanctions | ❌ | ❌ | ✅ |
| All data tables & analytics, ML Insights, audit trail | ❌ | ❌ | ✅ |

Cross-portal access always fails with a permission error (403), and the driver/officer sessions can never open admin screens or endpoints.

---

## 6. Data-integrity rules (why you can trust the numbers)

- **The ledger is append-only.** Points are never edited in place: corrections and deletions post reversal + repost entries. The running balance on every entry is recomputed, so the history is self-auditing.
- **Violations are soft-deleted** — rows are kept (with a deletion timestamp) for audit; they simply vanish from lists and reports.
- **Payments are create / void / verify only** — no editing or deleting money records; voiding requires a reason.
- **One transaction per business action** — a violation either fully succeeds (row + ledger + balance + risk + sanction + notification) or nothing is written.
- **All logins are audited** (`login_audits`: who, which portal, success/failure/lockout, IP, timestamp).
- **Owner pinning** — drivers can only ever create vehicles for themselves; the API ignores attempts to assign a vehicle to another user.

---

## 7. The ML part, in user terms

| You see | Behind it |
|---------|-----------|
| Risk score & class (LOW/MODERATE/HIGH) on violations and the driver gauge | Random-Forest model fed by the driver's history (days since last offence, speed-over-limit, zone, weather, demerit load, payment delays) |
| Top risk factors in explanations | SHAP explainability from the model |
| 3 driver segments + PCA scatter + segment profiles | K-Means clustering on 7 behavioural features (no demographics); k chosen by elbow + silhouette |
| Association rules & bubble chart | FP-Growth / Apriori mining over violation transactions (support/confidence/coverage/lift) |
| `ML: DOWN` badge + same screens still working | Deterministic heuristic fallback (`heuristic_v1_ml_down`) + ML results cached in MySQL |

To (re)train: run the scripts in `ml_service/` (`train_model.py`, `segmentation/train_kmeans.py`, `rules/mine_rules.py`) — the artifacts and curves are written back into MySQL, and the Insights page picks them up on refresh.

---

## 8. Troubleshooting

| Symptom | Fix |
|---------|-----|
| Page won't load at all | Start the frontend (`npm run dev`) — it serves `localhost:5173` |
| Every list shows "Failed to load" / empty | Start the backend (`artisan serve :8000`); check MySQL is running in XAMPP |
| Screens load but numbers look stale | They're live SQL — press the refresh button on the status strip / charts |
| `ML: DOWN` badge | The Python service isn't running — start `uvicorn app:app :8001`. Everything else keeps working meanwhile |
| Login feels slow, dashboards take ~10–20 s first time | Normal in development: PHP's built-in server is single-threaded — close extra tabs of the app |
| "Too many failed attempts…" | Locked for 15 minutes by design (or wait it out / use the correct credentials) |
| Officer can't find a driver/vehicle in the drop-downs | The rosters load in the background — wait a few seconds; they page through the **entire** list automatically |
| Want to reset demo data | Restore `apps/backend/traffic-demerit-web/backup.sql` via phpMyAdmin or the `mysql` CLI |
