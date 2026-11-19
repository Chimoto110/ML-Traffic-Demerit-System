# Project Overview — ML Traffic Demerit System

**Date:** 2026-10-17 · Companion doc: [FIX_REPORT.md](./FIX_REPORT.md) (root causes, fixes, demo logins, startup steps)

## What it is
A full-stack **traffic demerit-points management system** (NTSA-style, Kenya). Traffic
officers log violations against drivers and vehicles; each violation assigns **demerit
points** that accumulate on the driver's licence. An **ML pipeline** scores every driver's
risk of reoffending, segments drivers into behavioural profiles, and mines association
rules from violation patterns — all feeding dashboards, sanctions, appeals, and fine
payments (M-Pesa). Three role-separated portals (Driver, Officer, Admin) sit on top of one
Laravel API and one MySQL database.

## Architecture — 4 moving parts

| Part | Tech | Where | Role |
|------|------|-------|------|
| Portal | React 19 + Vite | `:5173` | Three role portals + ML Insights page |
| API | Laravel (Sanctum) | `:8000` | All business logic, auth, CRUD, chart aggregates |
| Database | MySQL `traffic_demerit` (XAMPP) | `:3306` | Single source of truth — **every chart is a SQL aggregate** |
| ML service | FastAPI + scikit-learn | `:8001` | Risk scoring, segmentation, rules — **optional**; system degrades gracefully |

The frontend talks **only** to the Laravel API. The Laravel API is the **only** client of
the Python ML service (`RiskInferenceService` is the single integration point).

## How the system works (the operational loop)

1. **Officer logs a violation** (`/officer`): driver, vehicle, offence type, speed recorded
   vs limit, zone type (urban/school zone/highway), weather, location, evidence reference.
2. **One atomic DB transaction** does everything:
   - inserts the `violations` row,
   - appends a `demerit_ledger_entries` row (points applied + running balance),
   - updates the driver's `current_demerit_points` (and account status at thresholds),
   - **scores the driver's risk** via ML (see below) → `risk_predictions` row,
   - evaluates sanctions (e.g., suspension triggers) → `sanctions` row,
   - notifies the driver (`system_notifications`, in-app + email-receipt rows).
3. **Driver portal** (`/driver`): demerit **gauge**, points-over-time chart, full
   append-only **ledger**, vehicles, sanctions, notifications, outstanding fines with
   **M-Pesa payment flow** (driver initiates → officer/admin verifies), and **appeals**
   against violations.
4. **Admin portal** (`/admin`): every table with server-side pagination/search/sort/filters,
   full CRUD, **Charts & Analytics** (risk distribution, monthly violations & fines, by
   offence type/station, payments summary, sanctions/appeals by status, live watch list,
   leaderboard), soft-delete with confirmation, ML Insights, and a live system-status strip
   (DB name, row counts, ML up/down).
5. **Integrity rules**: the ledger is **append-only** — officer edits (within 24 h) post a
   reversal + repost pair; admin deletes soft-delete the row and post a reversal. Nothing
   financial or historical is ever overwritten (payments are create/void/verify only).
6. **Auth**: separate login per portal (`/api/auth/{driver|officer|admin}/login`), Sanctum
   bearer tokens, generic "Invalid credentials for this portal" error (no role
   enumeration), 5 failures → **15-minute lockout (429)**, every attempt audited in
   `login_audits`. Only drivers self-register.

## The ML part

Everything lives in `apps/backend/traffic-demerit-web/ml_service/`. There are three
pipelines — one supervised, two unsupervised — plus a runtime service. Crucially,
**every ML artifact is also persisted into MySQL**, so all ML-flavoured visuals still
render when the Python service is off.

### 1. Feature pipeline — `etl/build_features.py`
Builds a per-driver feature table **`ml_driver_features`** straight from MySQL:
`violation_count`, `cumulative_active_demerit_points`, `avg_speed_over_limit`,
`past_fine_payment_delays_days`, `unpaid_fine_ratio`, `days_since_last_offense`,
`share_at_night`. **No demographics** — fairness by design.

### 2. Supervised risk model — `train_model.py` + `train_supervised.py`
- **RandomForest** classifier (`model/risk_model_rf_v2.joblib`) trained on:
  `days_since_last_offense`, `speed_recorded`, `zone_type`, `weather_conditions`,
  cumulative points, payment delays.
- `train_supervised.py` goes further: an **ablation study** (original 6 features →
  +segment → +rules → +both), comparing RandomForest / GradientBoosting /
  LogisticRegression, with probability **calibration**, **SHAP explanations**, and
  **fairness checks** per zone, weather, and segment.

### 3. Driver segmentation — `segmentation/train_kmeans.py` (unsupervised)
- **K-Means** on the 7 behavioural features above (StandardScaler first, `n_init ≥ 20`,
  fixed seed).
- Chooses *k* with the **elbow method** (WCSS k=1..10 + kneedle), cross-checked by
  **silhouette scores** (k=2..10) → settles on **k = 3** segments.
- Persists: `segment_models` (incl. `wcss_by_k` / `silhouette_by_k` JSON curves) and
  `driver_segments` (segment id + human label per driver, plus **`pca_x`/`pca_y`** — a
  PCA n=2 projection used for the scatter plot).

### 4. Association rules — `rules/mine_rules.py` (unsupervised)
- **Apriori + FP-Growth** (mlxtend) over violation transactions, two families:
  *risk rules* (→ reoffended within 180 days / late payment) and *context rules*
  (circumstance patterns, e.g. zone × weather × offence).
- Computes **support, confidence, coverage, lift**, and ranks by a transparent composite
  (never lift alone). Persisted to `association_rules`.

### 5. Runtime service — `app.py` (FastAPI, :8001)
Loads all joblib artifacts at startup and exposes:
- **`POST /v1/predict`** (+ legacy `/predict`) → `driver_risk_score`, `risk_class`
  (**low < 0.40 ≤ moderate < 0.70 ≤ high**), SHAP `explanation_top_factors`, confidence,
  and a matched context rule for the current violation circumstances.
- `POST /v1/segment`, `GET /v1/segments`, `GET /v1/rules`, `GET /v1/model-info`,
  `GET /health`.

### 6. How the app uses it — and survives without it
- On every violation, `RiskInferenceService` builds a feature snapshot from the driver's
  ledger/history and calls `/predict` (5 s timeout), storing the result in
  **`risk_predictions`**.
- **If the ML service is down** (`ConnectionException`), it falls back to a deterministic
  **heuristic** (`model_version = heuristic_v1_ml_down`): demerit load up to 0.45 +
  speeding up to 0.20 + school-zone 0.10 + offence recency 0.10/0.05 + payment
  delinquency up to 0.15 → same score/class contract. Violations, sanctions, and
  dashboards keep working; the admin footer shows **ML: DOWN** with a "dashboards fall
  back to MySQL data" note. (An HTTP *error* response, by contrast, is treated as a real
  failure and aborts the transaction — proven in the test suite.)
- The **ML Insights page** (`/ml-insights`, admin) is served by
  `/api/charts/ml-insights` **entirely from MySQL**: elbow curve, silhouette curve, PCA
  scatter of all 400 drivers coloured by segment, the 3 segment profiles (avg violations,
  points, speed-over-limit, unpaid ratio, fines, night share), and the association-rules
  table + bubble chart (support × confidence, bubble size = lift).

## Where to read more
- **[FIX_REPORT.md](./FIX_REPORT.md)** — root-cause table, files changed, demo logins,
  startup steps, design notes.
- **`docs/screenshots/`** — 19 end-to-end proof screenshots + `proof-results.json`.
- **`docs/proof/proof.mjs`** — re-runnable Playwright harness (40/40 UI↔DB checks).
- Demo logins: `admin@ntsa.go.ke`/`Admin@123`, `officer@ntsa.go.ke`/`Officer@123`,
  `driver@ntsa.go.ke`/`Driver@123`.
