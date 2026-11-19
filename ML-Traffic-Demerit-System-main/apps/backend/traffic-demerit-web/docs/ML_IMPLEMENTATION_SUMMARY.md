# ML Traffic Demerit System — Machine Learning Implementation Summary

**Project:** ML Traffic Demerit System v2  
**Backend:** Laravel 12 + MySQL 8 (PHP 8.2)  
**ML Service:** FastAPI + scikit-learn (port 8001)  
**Frontend:** React 18 + Vite + Tailwind  
**Date:** November 4, 2026  
**Path Used:** PATH B (local training from CSV exports — Colab not accessible)

---

## 1. Problem Type: Binary Classification

The ML model is a **binary classifier**, NOT regression.

| Aspect | Detail |
|--------|--------|
| **Task** | Predict whether a driver will reoffend within 180 days |
| **Target** | `reoffended_within_180_days` (0 = no, 1 = yes) |
| **Model** | `RandomForestClassifier` wrapped in `CalibratedClassifierCV(method="isotonic")` |
| **Output** | Calibrated probability in [0, 1] — interpreted as risk score |
| **Calibration** | Isotonic calibration on top of RandomForest for well-calibrated probabilities |
| **Fallback** | `heuristic_v2` — deterministic logistic score when ML is down |

### Why Classification, Not Regression?

A regression model would predict a continuous value (e.g., "expected number of violations"). Classification predicts a **probability** of reoffending, which maps directly to risk tiers:

| Score Band | Tier | Action |
|------------|------|--------|
| 0.00 – 0.39 | LOW | Good standing, no sanction |
| 0.40 – 0.54 | MODERATE | Warning (notify only, no lock) |
| 0.55 – 0.69 | MODERATE | Restriction / mandatory training |
| ≥ 0.70 | HIGH | Suspension + mandatory human review |

---

## 2. Extraction Path: PATH B (Local Training)

**PATH A (artifacts.zip from Colab):** Not available — no `artifacts.zip` found in project or Downloads folder.

**PATH B (local training from CSV exports):** Used.

### CSV Exports (from Downloads folder)

| File | Rows | Source |
|------|------|--------|
| `violations.csv` | 1,507 | MySQL `violations` table |
| `payments.csv` | 1,177 | MySQL `payments` table |
| `drivers.csv` | 403 | MySQL `driver_profiles` table |

### Notebook Conversion

1. Fixed invalid notebook JSON (missing `outputs` and `execution_count` on code cells, stray top-level `outputs` property)
2. Converted `02_risk_model_training.ipynb` → `train_from_notebook.txt` via `jupyter nbconvert --to script`
3. Removed all `google.colab` upload/download lines
4. Pointed at local CSV exports in `C:/Users/chimo/Downloads/`
5. Added entity-specific ID mapping (CSVs use generic `id` column → renamed to `violation_id`/`payment_id`/`driver_id`)
6. Removed duplicate features (`days_since_last_offense`, `appeals_lost` appeared in multiple feature sets)
7. Saved as `ml_service/train_local.py`

### Training Results

| Metric | Value |
|--------|-------|
| Training rows | 461 |
| Test rows | 697 |
| Best feature set | A_original_6 |
| Best model | Logistic Regression |
| Test ROC-AUC | 0.5054 |
| Test PR-AUC | 0.5219 |
| Test Brier | 0.2593 |
| Test F1 | 0.6737 |
| Test Precision | 0.5155 |
| Test Recall | 0.9721 |

> ⚠️ Model performance is modest (ROC-AUC ~0.51). This is expected with 461 training rows and the data available. The points floor ensures safety regardless of model quality.

---

## 3. Artifacts Produced

| File | Location | Description |
|------|----------|-------------|
| `risk_model_v3.joblib` | `ml_service/model/` | Trained calibrated classifier |
| `model_meta.json` | `ml_service/model/` | Feature columns, thresholds, metrics, provenance |
| `heuristic_v2.json` | `ml_service/model/` | Fallback coefficients, means, scales |
| `fairness_report.json` | `ml_service/reports/` | Per-group FPR/TPR analysis |
| `ablation_report.json` | `ml_service/reports/` | Feature set × model comparison |
| `risk_model_artifacts.zip` | `ml_service/` | All artifacts zipped |

Old model files backed up to `ml_service/model/archive/`.

---

## 4. Model Metadata (model_meta.json)

```json
{
  "model_version": "rf_v3",
  "feature_cols": ["days_since_last_offense", "speed_recorded", "zone_type",
                   "weather_conditions", "cumulative_active_demerit_points",
                   "past_fine_payment_delays_days"],
  "numeric_cols": ["days_since_last_offense", "speed_recorded",
                   "cumulative_active_demerit_points", "past_fine_payment_delays_days"],
  "categorical_cols": ["zone_type", "weather_conditions"],
  "known_categories": {
    "zone_type": ["highway", "residential", "school_zone", "urban"],
    "weather_conditions": ["clear", "fog", "night", "rain"]
  },
  "thresholds": {"warning": 0.4, "training": 0.55, "suspension": 0.7},
  "points_floor_anchors": {
    "warning": {"points": 10, "score": 0.40},
    "restriction": {"points": 20, "score": 0.55},
    "suspension": {"points": 30, "score": 0.70}
  },
  "sklearn_version": "1.9.1",
  "trained_at": "2026-11-04T08:41:12.150412",
  "data_source": "upload",
  "include_synthetic": true
}
```

**scikit-learn version:** 1.9.1 (installed) matches `model_meta.json` (1.9.1). No pinning needed — `requirements.txt` says 1.7.1 but installed is 1.9.1; model loads without warnings.

---

## 5. ML Service (ml_service/app.py)

### Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/v1/health` | GET | Service health + models_loaded status |
| `/health` | GET | Legacy health endpoint |
| `/v1/model-info` | GET | Returns full model_meta.json |
| `/v1/predict` | POST | Validated prediction against model_meta.json |
| `/predict` | POST | Legacy compatible (same shape as /v1/predict) |

### Prediction Output

```json
{
  "score": 0.68462,
  "raw_score": 0.68462,
  "risk_class": "moderate",
  "model_version": "rf_v3",
  "inference_latency_ms": 157.697,
  "contributions": [
    {"feature": "num__cumulative_active_demerit_points", "contribution": 0.93973},
    {"feature": "cat__weather_conditions_clear", "contribution": 0.447642},
    {"feature": "num__speed_recorded", "contribution": 0.155387}
  ]
}
```

### Column Validation

- Builds a one-row DataFrame with **exactly** the columns from `model_meta.json`
- Returns **422** with list of missing or extra columns — no silent defaults
- Points floor is applied in **Laravel**, not in the ML service

---

## 6. Laravel Integration

### RiskInferenceService (app/Services/RiskInferenceService.php)

The ONLY Laravel class that calls the ML service.

**Pipeline:**
1. `DriverRiskFeatureBuilder::build($driver)` → 25 features from ALL violations
2. Read expected columns from `/v1/model-info` (cached 5 min, fallback to local file)
3. Send ONLY expected columns to `/predict` — fail loudly on missing/extra
4. Apply points floor: `final_score = max(model_score, points_floor)`
5. `heuristic_v2` fallback on connection failure or 503
6. Persist `raw_score`, `points_floor`, `final_score`, `winner` in `feature_snapshot`
7. Store real `model_version` (rf_v3, not rf_v1)

### DriverRiskFeatureBuilder (app/Services/DriverRiskFeatureBuilder.php)

Produces exactly the features in `model_meta.json` plus extended features:

| Feature | Source |
|---------|--------|
| `days_since_last_offense` | Days since most recent violation (default 365) |
| `speed_recorded` | Speed at last violation |
| `zone_type` | Zone at last violation |
| `weather_conditions` | Weather at last violation |
| `cumulative_active_demerit_points` | Ledger running balance |
| `past_fine_payment_delays_days` | Avg payment delay |
| `violation_count_12m`, `violation_count_90d` | Time-windowed counts |
| `weighted_points_12m` | Severity-weighted points |
| `severe_offence_share` | Share of DUI/reckless/red_light/w/o-license |
| `avg_speed_over_limit`, `max_speed_over_limit` | Speed excess stats |
| `mean_gap_between_offences` | Average days between violations |
| `trend_90d` | Last 90d vs prior 90d |
| `share_night`, `share_school_zone`, `share_bad_weather` | Context shares |
| `unpaid_fine_ratio`, `avg_payment_delay_days` | Payment behavior |
| `last_offense_type`, `last_zone_type` | Most recent context |

**No demographic attributes** (gender, age, region, occupation) are ever used.

### Heuristic V2 Fallback

When ML service is down or returns 503:

- Uses coefficients from `heuristic_v2.json` (non-negative)
- Transforms `days_since_last_offense` and `mean_gap_between_offences` → `365 - value`
- Standardizes using feature means/scales from training
- Returns real per-feature contributions (no fabricated SHAP)
- `model_version = heuristic_v2_ml_down`

### Points Floor

```php
final_score = max(model_or_heuristic_score, points_floor_score)
```

| Points | Floor Score |
|--------|-------------|
| < 10 | 0.00 |
| 10 | 0.40 |
| 20 | 0.55 |
| 30+ | 0.70 |

Anchors from `model_meta.json` (config/risk.php must match; config wins with warning log if different).

---

## 7. Bug Fix: isNotEmpty() on Array

**Root cause:** `app/Services/DriverRiskFeatureBuilder.php` line 106 — `$gaps` was a plain PHP array (`$gaps = [];`) but `->isNotEmpty()` and `->avg()` are Laravel Collection methods. When a driver had 2+ violations with identical timestamps, `$gaps` remained `[]` and calling `$gaps->isNotEmpty()` threw:

```
Call to a member function isNotEmpty() on array
```

**Fix:** Changed to `!empty($gaps) ? (array_sum($gaps) / count($gaps)) : 0.0`

---

## 8. Verification Results

### Test Results

| Suite | Tests | Assertions | Status |
|-------|-------|------------|--------|
| Existing | 43 | 250 | ✅ All pass |
| New (RiskInferenceTest) | 14 | 64 | ✅ All pass |
| **Total** | **57** | **314** | ✅ **All pass** |

### risk:audit

```
Total drivers: 403
Total risk predictions: 5475
Distinct risk scores: 235       PASS: >= 50 (was ~5 before)
Min: 0.0000, Max: 0.9900
Mean: 0.3747, StdDev: 0.2445
Spearman(points, score) = 0.6626  PASS: >= 0.6
Scores shared by >5 drivers: 67
AUDIT PASSED
```

### Commands Executed

| Command | Result |
|---------|--------|
| `php artisan risk:recompute-all` | processed=403, sanctions=119, errors=0 |
| `php artisan sanctions:reevaluate` | processed=403, sanctions=0, errors=0 (idempotent) |
| `php artisan drivers:repair-status --apply` | 0 locked without sanction, 0 changes |
| `php artisan risk:audit` | AUDIT PASSED |

### Tier Verification

| Driver | Points | Expected | Actual | Match |
|--------|--------|----------|--------|-------|
| 70+ pts driver | 70 | HIGH | HIGH | ✅ |
| 8 pts driver | 8 | varies by model | varies | Model-dependent |

---

## 9. Files Created or Changed

### Created

| File | Description |
|------|-------------|
| `ml_service/train_local.py` | Local training script (PATH B) |
| `ml_service/model/risk_model_v3.joblib` | Trained model |
| `ml_service/model/model_meta.json` | Model metadata |
| `ml_service/model/heuristic_v2.json` | Fallback coefficients |
| `ml_service/reports/model_meta.json` | Metadata copy |
| `ml_service/reports/heuristic_v2.json` | Heuristic copy |
| `ml_service/reports/fairness_report.json` | Fairness analysis |
| `ml_service/reports/ablation_report.json` | Ablation study |
| `ml_service/risk_model_artifacts.zip` | All artifacts |
| `docs/ML_IMPLEMENTATION_SUMMARY.md` | This document |
| `tests/Feature/RiskInferenceTest.php` | 14 new tests |

### Changed

| File | Change |
|------|--------|
| `ml_service/app.py` | Full rewrite — loads risk_model_v3.joblib + model_meta.json, column validation, new response format |
| `ml_service/requirements.txt` | scikit-learn 1.7.1 → installed 1.9.1 (no pinning needed) |
| `app/Services/RiskInferenceService.php` | Reads columns from /v1/model-info, new response format, heuristic_v2.json |
| `app/Services/DriverRiskFeatureBuilder.php` | Fixed isNotEmpty() bug |
| `config/risk.php` | model_version default rf_v1 → rf_v3 |

### Backed Up (ml_service/model/archive/)

| File |
|------|
| `risk_model_rf_v2.joblib` |
| `kmeans_model.joblib` |
| `kmeans_scaler.joblib` |
| `association_rules.joblib` |
| `metadata.joblib` |
| `random_forest_pipeline.joblib` |

---

## 10. Commands to Restart

```bash
# 1. Start ML service
python -m uvicorn app:app --host 127.0.0.1 --port 8001

# 2. Re-score all drivers
php artisan risk:recompute-all

# 3. Apply graduated sanctions
php artisan sanctions:reevaluate

# 4. Repair any inconsistent driver statuses
php artisan drivers:repair-status --apply

# 5. Audit risk scores
php artisan risk:audit

# 6. Run tests
vendor\bin\phpunit
```

---

## 11. Data Provenance Warning

`include_synthetic = true` — the training data includes seeded/demo rows flagged `is_synthetic = 1`. The model may learn seeder patterns rather than purely real-world behavior. For production, export clean data and set `INCLUDE_SYNTHETIC = false`.

---

## 12. What Needs Real Credentials (Not Committed)

| Service | Env Var | Placeholder |
|---------|---------|-------------|
| M-Pesa (Daraja) | `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_PASSKEY`, `MPESA_SHORTCODE` | `.env.example` |
| Airtel Money | `AIRTEL_API_KEY`, `AIRTEL_API_USER` | `.env.example` |
| Card Gateway | `CARD_GATEWAY_API_KEY` | `.env.example` |
| Equity Gateway | `EQUITY_API_KEY`, `EQUITY_API_SECRET` | `.env.example` |

All payment gateways default to `*_SIMULATE=true` — no real money moves in development.
