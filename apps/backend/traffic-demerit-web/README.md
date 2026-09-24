# Traffic Demerit Web

Traffic demerit enforcement platform with automated recidivism scoring and sanctions.

## Predictive Engine Policy

The system uses one predictive engine only:

- `RandomForestClassifier` from `scikit-learn`
- No deep learning or neural networks in the sanctioning pipeline

This choice targets structured/tabular enforcement data, lightweight infrastructure, and model interpretability through feature-importance signals.

## What the model outputs

For every scored driver profile:

- `driver_risk_score`: continuous probability (`0.0` to `1.0`)
- `risk_class`: `low`, `moderate`, or `high`

## Approved input features

Only these non-demographic features are sent to inference:

- `days_since_last_offense`
- `speed_recorded`
- `zone_type`
- `weather_conditions`
- `cumulative_active_demerit_points`
- `past_fine_payment_delays_days`

Excluded by policy: demographic attributes such as gender, region, and occupation.

## Pipeline

1. Officer submits violation via `POST /api/violations`.
2. Violation and demerit ledger entry are persisted.
3. `RiskInferenceService` builds the feature vector and calls ML `/predict`.
4. If risk score crosses `RISK_SANCTION_THRESHOLD`, `SanctionService` locks the profile (`profile_suspension`) and stores factor-based trigger reasoning.
5. `risk:batch-infer` command re-scores drivers periodically (scheduled hourly).

## Relational Schema Clusters (Expanded)

The implementation now covers 13 entities grouped into five functional clusters.

Identity and access:

- `users`
- `roles`
- `driver_profiles`
- `vehicles`

Field operations:

- `stations`
- `officer_assignments`

Violation-to-sanction pipeline:

- `violations`
- `demerit_ledger_entries`
- `payments`
- `sanction_actions`
- `appeals`
- `review_logs`

ML prediction layer:

- `risk_predictions`
- `risk_assessments` (kept for backward compatibility and integration payload continuity)

Key links now enforced in schema:

- User-to-role (`users.role_id`)
- User-to-vehicle ownership (`vehicles.owner_user_id`)
- User-to-driver profile ownership (`driver_profiles.user_id`)
- Station-to-officer assignment (`officer_assignments.station_id`)
- Assignment-to-violation accountability (`violations.officer_assignment_id`)
- Vehicle-to-violation (`violations.vehicle_id`)
- Violation-to-ledger (`demerit_ledger_entries.violation_id`)
- Ledger-to-payment (`payments.ledger_entry_id`)
- Driver-to-risk prediction history (`risk_predictions.driver_id`)
- Prediction-to-sanction (`sanction_actions.risk_prediction_id`)
- Sanction-to-review log (`review_logs.sanction_action_id`)
- Sanction-to-appeal (`appeals.sanction_action_id`)

## Laravel Configuration

Environment variables:

- `ML_SERVICE_URL` (default: `http://127.0.0.1:8001`)
- `RISK_SANCTION_THRESHOLD` (default: `0.7`)
- `RISK_MODEL_VERSION` (default: `rf_v1`)
- `PEXELS_API_KEY` (required for downloading profile pictures)

## Driver Profile Pictures (Pexels)

Driver profiles now support photo avatars via the `profile_photo_path` field.

To fetch and attach portraits from Pexels:

```bash
php artisan drivers:sync-pexels-photos --limit=30
```

Options:

- `--limit`: number of driver profiles to update
- `--force`: replace existing photos

Downloaded files are stored in `public/driver-photos/` and exposed by the integration API as `profile_photo_url`.

Note: the sync command uses Kenya-focused search queries and requires manual review. Nationality cannot be guaranteed automatically from image metadata.

## ML Service Setup

See `ml_service/README.md` for training and serving the Random Forest model.

Quick start:

```bash
cd ml_service
pip install -r requirements.txt
python train_model.py --input path/to/simulated_dataset.csv
uvicorn app:app --host 127.0.0.1 --port 8001 --reload
```

## Batch Inference Scheduler

Defined in `routes/console.php`:

- Command: `php artisan risk:batch-infer`
- Schedule: hourly

To run scheduler locally:

```bash
php artisan schedule:work
```

## Production Readiness Checks

Run a health and readiness report that validates:

- database connectivity
- ML service health and latency
- batch inference freshness
- queue backlog / failed jobs
- sanction webhook failure rate
- inference latency percentiles (p50 / p95)

```bash
php artisan system:readiness-check
```

The report is emitted as JSON for easy capture in deployment logs.

## PostgreSQL Migration Pathway

The codebase is database-portable through Laravel and supports PostgreSQL using the built-in `pgsql` connection.

1. Set environment values:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=traffic_demerit
DB_USERNAME=postgres
DB_PASSWORD=your_password
DB_SSLMODE=prefer
```

2. Clear and warm config cache:

```bash
php artisan config:clear
php artisan config:cache
```

3. Run migrations on PostgreSQL:

```bash
php artisan migrate --force
```

4. Run readiness diagnostics to validate the environment:

```bash
php artisan system:readiness-check
```

## Evaluation

Model training script reports:

- Precision / Recall / F1 via classification report
- ROC-AUC

Functional and user acceptance testing should still be run at application level for end-to-end behavior.
