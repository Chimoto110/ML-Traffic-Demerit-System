# ML Traffic Demerit System — Machine Learning Summary

**Project:** ML Traffic Demerit System v2  
**Backend:** Laravel 12 + MySQL 8 (PHP 8.2)  
**ML Service:** FastAPI + scikit-learn (port 8001)  
**Frontend:** React 18 + Vite + Tailwind  
**Date:** November 2026

---

## 1. Problem Type: Binary Classification

The ML model is a **binary classifier**, NOT a regression model.

| Aspect | Detail |
|--------|--------|
| **Task** | Predict whether a driver will reoffend within 180 days |
| **Target** | `reoffended_within_180_days` (0 = no, 1 = yes) |
| **Model** | RandomForestClassifier wrapped in `CalibratedClassifierCV(method="isotonic")` |
| **Output** | Probability score in [0, 1] — interpreted as risk score |
| **Calibration** | Isotonic calibration on top of RandomForest for well-calibrated probabilities |
| **Fallback** | `heuristic_v2` — deterministic weighted logistic score (no ML dependency) |

### Why Classification, Not Regression?

The original notebook (01_preprocessing.ipynb) was a regression problem predicting `unitsSold` (continuous). This project reframed it as **binary classification**: given a driver's history, will they commit another violation within 180 days? The output probability directly maps to the risk tiers:

| Score Band | Tier | Action |
|------------|------|--------|
| 0.00 – 0.39 | LOW | Good standing, no sanction |
| 0.40 – 0.54 | MODERATE | Warning (notify only, no lock) |
| 0.55 – 0.69 | MODERATE | Restriction / mandatory training |
| ≥ 0.70 | HIGH | Suspension + mandatory human review |

---

## 2. ML Pipeline Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     Laravel Backend (PHP 8.2)                   │
│                                                                 │
│  RiskInferenceService (ONLY class that calls ML service)        │
│  ├── DriverRiskFeatureBuilder → builds per-driver feature vector│
│  ├── HTTP POST to FastAPI /predict                              │
│  ├── Points floor: final_score = max(model_score, floor)        │
│  ├── heuristic_v2 fallback when ML is down                      │
│  └── Persists RiskAssessment + RiskPrediction rows              │
└─────────────────────────────────────────────────────────────────┘
                          │ HTTP/JSON
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                  FastAPI ML Service (Python, :8001)             │
│                                                                 │
│  app.py — models loaded once at startup                         │
│  ├── risk_model_rf_v2.joblib  → calibrated RandomForest         │
│  ├── kmeans_model.joblib      → k-Means segmentation            │
│  ├── kmeans_scaler.joblib     → StandardScaler                  │
│  └── association_rules.joblib  → FP-Growth mined rules          │
│                                                                 │
│  POST /v1/predict → { driver_risk_score, risk_class,            │
│                      explanation_top_factors, segment_label }   │
└─────────────────────────────────────────────────────────────────┘
```

### Data Flow (Prediction Path)

1. **Frontend** calls `POST /api/integration/functions/predictAccidentRisk` with `driver_id`
2. **IntegrationController** → `RiskInferenceService::assessDriver()`
3. **DriverRiskFeatureBuilder::build($driver)** computes ~24 features from ALL of the driver's non-deleted violations
4. **RiskInferenceService** sends feature vector to `http://127.0.0.1:8001/predict`
5. **FastAPI** loads `risk_model_rf_v2.joblib`, runs `predict_proba`, returns `{ driver_risk_score, risk_class, explanation_top_factors }`
6. **RiskInferenceService** applies points floor: `final_score = max(raw_score, points_floor)`
7. **RiskAssessment + RiskPrediction** rows created with `risk_score = final_score`
8. **SanctionService::evaluate()** applies graduated sanctions based on `final_score`
9. **DriverStatusSynchronizer::sync()** sets `driver_profiles.status` (only writer of this column)

---

## 3. Features Used by the ML Model

### Core Features (sent to ML model)

| Feature | Type | Description |
|---------|------|-------------|
| `days_since_last_offense` | numeric | Days since most recent violation |
| `speed_recorded` | numeric | Speed recorded at last violation |
| `zone_type` | categorical | urban / highway / school_zone / residential |
| `weather_conditions` | categorical | clear / rain / fog / night |
| `cumulative_active_demerit_points` | numeric | Running balance from demerit ledger |
| `past_fine_payment_delays_days` | numeric | Average days from violation to payment |
| `violation_count` | numeric | Total non-deleted violations |
| `new_driver` | boolean | Fewer than 2 violations |
| `segment_id` | numeric | k-Means cluster assignment (-1 if none) |
| `distance_to_centroid` | numeric | Distance to assigned cluster centroid |
| `rule_hit_count` | numeric | Number of matching association rules |

### Extended Features (computed by DriverRiskFeatureBuilder, stored in feature_snapshot)

| Feature | Description |
|---------|-------------|
| `violation_count_12m` | Violations in last 12 months |
| `violation_count_90d` | Violations in last 90 days |
| `weighted_points_12m` | Severity-weighted points (last 12 months) |
| `severe_offence_share` | Share of DUI/reckless/red_light/w/o-license |
| `avg_speed_over_limit` | Average speed excess over posted limit |
| `max_speed_over_limit` | Maximum speed excess over posted limit |
| `mean_gap_between_offences` | Average days between consecutive violations |
| `trend_90d` | Last 90d vs prior 90d violation count trend |
| `share_night` | Share of violations at night |
| `share_school_zone` | Share of violations in school zones |
| `share_bad_weather` | Share of violations in rain/fog/storm |
| `unpaid_fine_ratio` | Ratio of unpaid fines to total fines |
| `avg_payment_delay_days` | Average payment delay in days |
| `last_offense_type` | Most recent offence type |
| `last_zone_type` | Most recent zone type |
| `appeals_lost` | Count of rejected appeals |

**Demographic exclusion:** No gender, age, region, occupation, or any demographic proxy is ever used as a feature.

---

## 4. Model Training (train_supervised.py)

### Training Data Construction

- **Source:** `ml_driver_features` table (populated by `etl/build_features.py` from MySQL)
- **One row per driver:** Latest violation per driver joined with feature store
- **Target:** `reoffended_within_180_days` — driver has a 2nd+ violation within 180 days of first
- **Feature sets (ablation):**
  - **A:** Original 6 features only
  - **B:** A + segmentation features
  - **C:** A + rule_hit_count
  - **D:** A + segmentation + rules

### Model Comparison

| Model | Parameters |
|-------|------------|
| RandomForestClassifier | 300 estimators, max_depth=10, min_samples_leaf=3, class_weight=balanced |
| GradientBoostingClassifier | 200 estimators, max_depth=5 |
| LogisticRegression | max_iter=2000, class_weight=balanced |

### Cross-Validation

- **Strategy:** StratifiedKFold (5 splits, shuffle, random_state=42)
- **Metrics:** ROC-AUC (mean ± std), F1, Precision, Recall

### Final Model

- **Algorithm:** RandomForestClassifier (best from ablation)
- **Calibration:** `CalibratedClassifierCV(method="isotonic", cv=3)` — converts raw RF votes to calibrated probabilities
- **Save path:** `ml_service/model/risk_model_rf_v2.joblib`
- **Artifact contents:** `{ model, feature_cols, numeric_cols, categorical_cols, model_version }`

### Fairness Evaluation

- **Groups:** zone_type, weather_conditions, segment_id
- **Metrics per group:** FPR (false positive rate), TPR (true positive rate), n (count), FPR ratio to overall
- **Flagging:** Groups with FPR > 1.25× overall FPR are flagged
- **Output:** `ml_service/reports/fairness_report.json`

### Ablation Report

- **Output:** `ml_service/reports/ablation_report.json`
- **Content:** ROC-AUC, F1, Precision, Recall for each feature set × model combination

---

## 5. Points Floor (Score Floor)

The points floor ensures that a driver's accumulated demerit points always set a minimum risk score, regardless of what the ML model predicts.

### Anchors (config/risk.php)

| Points | Floor Score | Tier |
|--------|-------------|------|
| 0–9 | 0.00 | Good standing |
| 10 | 0.40 | Warning |
| 20 | 0.55 | Restricted |
| 30+ | 0.70 | Suspended |

### Formula

- **Below 10 points:** Floor = 0.0
- **10–29 points:** Linear interpolation between (10→0.40) and (30→0.70)
- **30+ points:** Floor = 0.70
- **Cap:** Maximum floor score = 0.99

### Final Score

```
final_score = max(model_or_heuristic_score, points_floor_score)
```

This is stored in:
- `risk_assessments.feature_snapshot.final_score`
- `risk_predictions.risk_score` (= final_score)

---

## 6. Heuristic V2 Fallback

When the FastAPI ML service is down, `RiskInferenceService` falls back to a deterministic heuristic:

```
score = 0.0
  + min(0.45, (balance / 30) * 0.45)           # demerit load
  + min(0.20, (max(0, speed - 80) / 40) * 0.20) # speed excess (clamped >= 0)
  + (zone === 'school_zone' ? 0.10 : 0.0)       # zone risk
  + (days_since < 30 ? 0.10 : days_since < 90 ? 0.05 : 0.0)  # recency
  + min(0.15, (delay / 60) * 0.15)              # payment delinquency
  + min(0.10, (violation_count / 10) * 0.10)    # violation count
```

All terms clamped ≥ 0. Points floor applied afterward. Returns real per-feature contributions (no fabricated SHAP).

---

## 7. Segmentation (k-Means)

- **Algorithm:** k-Means clustering
- **Features:** violation_count, cumulative_active_demerit_points, avg_speed_over_limit, past_fine_payment_delays_days, unpaid_fine_ratio, days_since_last_offense, share_at_night
- **Preprocessing:** StandardScaler
- **Save:** `kmeans_model.joblib`, `kmeans_scaler.joblib`
- **Stored in DB:** `segment_models` table (centroids, silhouette, WCSS), `driver_segments` table (driver → segment mapping)

---

## 8. Association Rules (FP-Growth)

- **Algorithm:** FP-Growth (via `ml_service/rules/mine_rules.py`)
- **Input:** One-hot encoded violation transactions from `ml_transactions` table
- **Output:** Rules with antecedent → consequent, lift, confidence, support
- **Save:** `association_rules.joblib`
- **Usage:** At prediction time, match the driver's zone_type + weather_conditions against rule antecedents to find the top matching risk rule

---

## 9. ETL Pipeline (etl/build_features.py)

```
extract → transform → load
```

1. **Extract:** violations, ledger entries, payments, drivers, sanctions, appeals from MySQL
2. **Transform:** Per-driver feature rows + per-violation transaction rows
3. **Load:** Upsert into `ml_driver_features` and `ml_transactions` tables (idempotent — safe to re-run)

---

## 10. Colab Notebook (02_risk_model_training.ipynb)

**File:** `apps/backend/traffic-demerit-web/ml_service/02_risk_model_training.ipynb`

### Steps (adapted from 01_preprocessing.ipynb in-place):

| Step | Description |
|------|-------------|
| Step 0 | Configuration (DATA_SOURCE, INCLUDE_SYNTHETIC, label horizon, etc.) |
| Step 1 | Upload/inspect violations.csv, payments.csv, drivers.csv |
| Step 2 | Cleaning (column alias auto-detection, date parsing, missing value handling) |
| Step 3 | Feature engineering + leakage removal (time-based cutoffs, no future data) |
| Step 4 | One-hot encoding of categorical features |
| Step 5 | Scaling proof (RF, KNN, Logistic Regression scored by ROC-AUC) |
| Step 6 | Training & evaluation (RandomForest with ROC-AUC, PR-AUC, Brier score) |
| Step 7 | Baselines (base rate, demerit points only, violations in last 90 days) |
| Step 8 | Leave-drivers-out cross-validation (group K-fold by driver_id) |
| Step 9 | Sanction-tier check (share of drivers per tier vs real reoffending rate) |
| Save | risk_model_v3.joblib + model_meta.json |

### Allowed Additions (4 cells):

- **A.** Calibration: `CalibratedClassifierCV(method="sigmoid")` with driver-grouped splits; distinct score count vs isotonic
- **B.** Points floor: `points_floor()` function + `final_score = max(model, floor)` table; export `heuristic_v2.json`
- **C.** Robustness: auto-detect column aliases, warn on tiny/unbalanced labels, skip uncomputable metrics
- **D.** Save extras: `model_meta.json` + `files.download("artifacts.zip")`

### Artifacts Produced

| File | Destination |
|------|-------------|
| `risk_model_rf_v2.joblib` | `ml_service/model/` |
| `kmeans_model.joblib` | `ml_service/model/` |
| `kmeans_scaler.joblib` | `ml_service/model/` |
| `association_rules.joblib` | `ml_service/model/` |
| `ablation_report.json` | `ml_service/reports/` |
| `fairness_report.json` | `ml_service/reports/` |

---

## 11. Random State & Reproducibility

- **Random state:** 42 (all ML components: train/test split, CV, RandomForest, LogisticRegression, k-Means)
- **Environment:** Python 3.10, scikit-learn (matching version in `ml_service/requirements.txt`)
- **Deterministic:** Same data → same model → same predictions

---

## 12. Bug Fix: isNotEmpty() on Array

**Root cause:** In `app/Services/DriverRiskFeatureBuilder.php`, line 106, `$gaps` was declared as a plain PHP array (`$gaps = [];`) but `->isNotEmpty()` and `->avg()` are Laravel Collection methods. When a driver had 2+ violations with identical `occurred_at` timestamps (no gaps), `$gaps` remained `[]` and calling `$gaps->isNotEmpty()` threw:

```
Call to a member function isNotEmpty() on array
```

**Fix:** Changed to `!empty($gaps) ? (array_sum($gaps) / count($gaps)) : 0.0` — plain PHP array operations.

**File changed:** `app/Services/DriverRiskFeatureBuilder.php`

---

## 13. Commands to Run (in order)

```bash
# 1. Run database migrations
php artisan migrate

# 2. Seed demo driver accounts
php artisan db:seed --class=DemoDriverAccountsSeeder

# 3. Train the ML model (Colab notebook)
#    - Open 02_risk_model_training.ipynb in Google Colab
#    - Upload violations.csv, payments.csv, drivers.csv exports
#    - Run all cells, download artifacts.zip
#    - Extract and copy to ml_service/model/

# 4. Re-score all drivers with the new model
php artisan risk:recompute-all

# 5. Apply graduated sanctions
php artisan sanctions:reevaluate

# 6. Repair any inconsistent driver statuses
php artisan drivers:repair-status --apply

# 7. Audit risk scores (distinct count, Spearman correlation)
php artisan risk:audit

# 8. Verify system health
php artisan system:readiness-check
php artisan demo:verify
vendor\bin\phpunit
```

---

## 14. Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `ML_SERVICE_URL` | `http://127.0.0.1:8001` | FastAPI ML service URL |
| `RISK_MODEL_VERSION` | `rf_v1` | Model version label |
| `RISK_WARNING_THRESHOLD` | `0.40` | Warning tier lower bound |
| `RISK_TRAINING_THRESHOLD` | `0.55` | Restriction tier lower bound |
| `RISK_SANCTION_THRESHOLD` | `0.7` | Suspension tier lower bound |
| `RISK_POINTS_WARNING` | `10` | Points floor: warning anchor |
| `RISK_POINTS_RESTRICTION` | `20` | Points floor: restriction anchor |
| `RISK_POINTS_SUSPENSION` | `30` | Points floor: suspension anchor |
| `RISK_POINTS_FLOOR_MAX` | `0.99` | Maximum floor score |

---

## 15. Files Changed / Created

### PHP (Backend)

| File | Action |
|------|--------|
| `app/Services/DriverRiskFeatureBuilder.php` | **FIXED** — `isNotEmpty()` on array → `!empty()` + `array_sum()/count()` |
| `app/Services/RiskInferenceService.php` | Full rewrite — full feature vector, points floor, heuristic_v2 fallback |
| `app/Services/SanctionService.php` | Graduated sanctions on final_score, trigger_reason with floor/risk |
| `app/Console/Commands/RepairStatus.php` | Status repair command (--apply flag) |
| `config/risk.php` | Sanction thresholds + points floor anchors |
| `config/offenses.php` | **NEW** — Single offense catalog (points, fines, severity) |
| `app/Http/Controllers/IntegrationController.php` | predictAccidentRisk returns stable risk object |
| `app/Http/Controllers/Api/ViolationController.php` | Violation store with risk inference + sanction |

### Python (ML Service)

| File | Action |
|------|--------|
| `ml_service/app.py` | FastAPI — /v1/predict, /v1/segment, /v1/rules, /v1/model-info |
| `ml_service/train_supervised.py` | Training with ablation, calibration, fairness |
| `ml_service/etl/build_features.py` | ETL for ml_driver_features + ml_transactions |
| `ml_service/02_risk_model_training.ipynb` | Colab notebook for real-database training |

### Frontend (React)

| File | Action |
|------|--------|
| `src/components/RiskGauge.jsx` | Animated SVG gauge (4 zones, needle, readout) |
| `src/components/DriverSelect.jsx` | Searchable driver combobox (no duplicates) |
| `src/components/ui/slider.jsx` | Slider component for demo page |
| `src/pages/DevGauge.jsx` | /dev/gauge demo page with all 4 zones |
| `src/lib/constants.js` | RISK_TIERS, POINTS_FLOOR_ANCHORS, tierForScore() |

### Tests

- **43/43 Laravel tests pass** (250 assertions)
- **Frontend build succeeds** (zero lint errors)
- **All PHP syntax checks pass**

---

## 16. Demo Credentials

| Role | Email | Password | Points | Status |
|------|-------|----------|--------|--------|
| Admin | admin@ntsa.go.ke | Admin@123 | — | — |
| Officer | officer@ntsa.go.ke | Officer@123 | — | — |
| Driver | driver@ntsa.go.ke | Driver@123 | — | — |
| Ngugi Wangechi | ngugi.wangechi@example.com | Ngugi@2026 | 71 | Suspended |
| Thuo Njoki | thuo.njoki@example.com | Thuo@2026 | 70 | Suspended |
| Gathoni Wangui | gathoni.wangui@example.com | Gathoni@2026 | 65 | Suspended |
| Njenga Nyambura | njenga.nyambura@example.com | Njenga@2026 | 64 | Suspended |
| Mwangi Njeri | mwangi.njeri@example.com | Mwangi@2026 | 28 | Warning |
| Macharia Nduta | macharia.nduta@example.com | Macharia@2026 | 8 | Active |
| Grace Wanjiru | grace@example.com | Driver@123 | 6 | Active |

**⚠️ Demo only — never use in production.**

---

## 17. How to Demo Each Component

### Risk Prediction
1. Log in as admin or officer
2. Navigate to Predict Accident Risk
3. Select a driver (e.g., Ngugi Wangechi, 71 pts)
4. Click Predict — gauge animates to final score, shows tier (Suspended for ≥0.70)
5. ML service down → heuristic_v2 fallback kicks in automatically

### Graduated Sanctions
- 0.40–0.54: Warning notification (no lock)
- 0.55–0.69: Restricted + mandatory training
- ≥0.70: Suspended + review_logs row + NTSA webhook

### M-Pesa STK Push
1. Driver logs in, goes to Pay Fine
2. Selects M-Pesa, enters phone (+2547XXXXXXXX)
3. Backend initiates STK push, UI says "Check your phone and enter your PIN"
4. Polls payment status every 3 seconds
5. On success: violation settled, receipt generated

### DriverSelect (No Duplicate Names)
- Each option shows "Name - DL-KE-XXXX - XX pts" with tier-coloured dot
- Server-side debounced search, no duplicate keys
- Works at 320px mobile width

---

## 18. What Needs Real Credentials (Not Committed)

| Service | Env Var | Placeholder |
|---------|---------|-------------|
| M-Pesa (Daraja) | `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_PASSKEY`, `MPESA_SHORTCODE` | `.env.example` |
| Airtel Money | `AIRTEL_API_KEY`, `AIRTEL_API_USER` | `.env.example` |
| Card Gateway | `CARD_GATEWAY_API_KEY` | `.env.example` |
| Equity Gateway | `EQUITY_API_KEY`, `EQUITY_API_SECRET` | `.env.example` |
| NTSA Webhook | `NTSA_WEBHOOK_URL` | `.env.example` |

All payment gateways default to `*_SIMULATE=true` — no real money moves in development.
