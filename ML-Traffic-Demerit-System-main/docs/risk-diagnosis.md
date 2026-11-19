# Risk Diagnosis - Phase 0 Findings

## Executive Summary

Diagnosis of the ML Traffic Demerit System risk scoring pipeline identified three root causes:

1. **Risk score uniformity**: 174 distinct values across 4062 risk_predictions rows, but clustering exists at round numbers (0.9800 x 89, 0.9700 x 2, etc.) suggesting discrete calibration steps from isotonic calibration.
2. **Locked vs risk class mismatch**: 272 drivers with non-active status, many with LOW/MODERATE risk class — status and risk are decoupled.
3. **Demerit points blindness**: Multiple direct writers of `driver_profiles.status` bypass the sanction pipeline; 179 locked drivers have NO active `sanction_actions` row.

## Diagnosis Details

### A. Risk Score Uniformity

Query: `SELECT COUNT(DISTINCT risk_score) FROM risk_predictions` → **174 distinct values** across 4062 rows.

Distribution (top 10):
| Score | Count |
|-------|-------|
| 0.9800 | 89 |
| 0.9750 | 2 |
| 0.9700 | 2 |
| ... | ... |

**Root cause**: `CalibratedClassifierCV(method='isotonic')` in `train_supervised.py` produces a step function — many inputs map to the same output probability. The isotonic calibration is piecewise-constant, creating clusters at round-number boundaries.

### B. Locked vs Risk Class Mismatch

Query: Drivers with `status != 'active'` → **272 drivers** (out of ~400).

Query: Locked drivers with risk_class in (LOW, MODERATE) → **2548** (join duplicates due to multiple risk_predictions per driver).

**Examples**:
- Thuo Njoki: status=suspended, score=0.5586, class=moderate

**Root cause**: `SanctionService::applyLocalEffect()` writes `driver_profiles.status` directly (line 244), bypassing the sanction pipeline. `PaymentController::completePayment()` also writes `status='active'` directly (line 250). Multiple writers create inconsistent state.

### C. Demerit Points Blindness

The ML model uses only 6 features from `train_model.py`: `days_since_last_offense`, `speed_recorded`, `zone_type`, `weather_conditions`, `cumulative_active_demerit_points`, `past_fine_payment_delays_days`.

However, `RiskInferenceService::buildFeatureSnapshot()` (line 101-207) only passes the LATEST violation's speed/zone/weather, defaulting missing values to 0 or 'urban'/'clear'. Drivers with sparse violation history get the same defaults regardless of their actual demerit balance.

### D. Status Writers

Files that write `driver_profiles.status` directly:
1. `app/Services/SanctionService.php` line 244: `applyLocalEffect()` → `$driver->update(['status' => $statusMap[$actionType]])`
2. `app/Http/Controllers/PaymentController.php` line 250: `$driver->update(['status' => 'active'])`
3. `app/Http/Controllers/ViolationController.php` line 123-125: `account_status` field (not `status` but related)

**179 locked drivers have NO active sanction_actions row** — their status was set by payment completion or other direct writes, not by the sanction pipeline.

## Remediation Plan (Phases 1-2)

### Phase 1 — Per-Driver Risk Scoring
- Create `DriverRiskFeatureBuilder` computing features from ALL of a driver's violations (not just latest)
- Replace isotonic calibration with sigmoid (Platt) calibration for continuous output
- Rewrite heuristic fallback as `heuristic_v2` with real per-feature contributions
- Apply points floor: `final_score = max(model_score, points_floor)` where points_floor scales linearly from anchors (10→0.40, 20→0.55, 30→0.70)
- Return stable `meta.risk` object from POST /api/violations
- Commands: `risk:recompute-all`, `risk:audit`

### Phase 2 — Sanctions + Lock Status
- `SanctionService::evaluate()` uses `final_score` tiers with trigger_reason
- Create `DriverStatusSynchronizer::sync($driver)` — single source of truth for status
- Remove all direct `driver_profiles.status` writes except via DriverStatusSynchronizer
- Appeals unification: one AppealService for both API paths
- Payments never lift risk/points suspensions
- Commands: `sanctions:reevaluate`, `drivers:repair-status --apply`