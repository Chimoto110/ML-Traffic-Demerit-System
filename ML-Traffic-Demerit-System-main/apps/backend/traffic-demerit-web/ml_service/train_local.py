"""
Train risk model locally from CSV exports (PATH B - no Colab access).
Converted from 02_risk_model_training.ipynb with google.colab lines removed.
"""

from __future__ import annotations

import json
import os
import shutil
import zipfile
from datetime import datetime, timedelta
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sklearn.calibration import CalibratedClassifierCV
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import RandomForestClassifier, GradientBoostingClassifier
from sklearn.impute import SimpleImputer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (
    brier_score_loss,
    f1_score,
    precision_score,
    recall_score,
    roc_auc_score,
    average_precision_score,
)
from sklearn.model_selection import StratifiedKFold, train_test_split
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

# ============================
# CONFIGURATION — EDIT THESE VALUES
# ============================

DATA_SOURCE = "upload"  # real CSVs
INCLUDE_SYNTHETIC = True  # Include is_synthetic=1 rows from your database export
LABEL_HORIZON_DAYS = 180   # Reoffence window in days
CUTOFF_EVERY_DAYS = 14     # Spacing between time-based training cutoffs
MIN_TRAIN_ROWS = 2000      # Minimum training rows required

# Fine per demerit point (KES) — must match your backend
FINE_PER_POINT = 500

# Sanction thresholds (applied to final risk score)
RISK_WARNING_THRESHOLD = 0.40
RISK_TRAINING_THRESHOLD = 0.55
RISK_SANCTION_THRESHOLD = 0.70

# Points-floor anchors for risk floor calculation
POINTS_FLOOR_ANCHORS = {
    "warning": {"points": 10, "score": 0.40},
    "restriction": {"points": 20, "score": 0.55},
    "suspension": {"points": 30, "score": 0.70},
}

# Offense → points mapping (must match your backend offense catalog)
OFFENSE_POINTS = {
    "speeding": 4,
    "illegal_parking": 2,
    "dui": 10,
    "reckless_driving": 8,
    "red_light": 5,
    "no_seatbelt": 2,
    "phone_use": 3,
    "driving_without_license": 8,
    "overloading": 4,
    "no_insurance": 6,
}

# Random seed for reproducibility
RANDOM_STATE = 42

# Paths to CSV exports (Downloads folder)
CSV_DIR = Path(r"C:/Users/chimo/Downloads")
VIOLATIONS_CSV = CSV_DIR / "violations.csv"
PAYMENTS_CSV = CSV_DIR / "payments.csv"
DRIVERS_CSV = CSV_DIR / "drivers.csv"

# Output directories
MODEL_DIR = Path(__file__).resolve().parent / "model"
REPORTS_DIR = Path(__file__).resolve().parent / "reports"
MODEL_DIR.mkdir(parents=True, exist_ok=True)
REPORTS_DIR.mkdir(parents=True, exist_ok=True)

print("✓ Configuration loaded")
print(f"  DATA_SOURCE = {DATA_SOURCE}")
print(f"  INCLUDE_SYNTHETIC = {INCLUDE_SYNTHETIC}")
print(f"  LABEL_HORIZON_DAYS = {LABEL_HORIZON_DAYS}")
print(f"  CUTOFF_EVERY_DAYS = {CUTOFF_EVERY_DAYS}")
print(f"  MIN_TRAIN_ROWS = {MIN_TRAIN_ROWS}")
print(f"  FINE_PER_POINT = {FINE_PER_POINT} KES")
print(f"  RISK thresholds: warning={RISK_WARNING_THRESHOLD}, training={RISK_TRAINING_THRESHOLD}, suspension={RISK_SANCTION_THRESHOLD}")
print(f"  Points-floor anchors: {POINTS_FLOOR_ANCHORS}")
print(f"  CSV dir: {CSV_DIR}")

# ----------------------------------------------------------------------
# Step 1: Load CSV exports (local files, no google.colab)
# ----------------------------------------------------------------------
print("\n📂 Loading CSV exports from local filesystem...")
for p in [VIOLATIONS_CSV, PAYMENTS_CSV, DRIVERS_CSV]:
    if not p.exists():
        raise FileNotFoundError(f"❌ CSV not found: {p}\n   Please export from MySQL and place in {CSV_DIR}")

violations_df = pd.read_csv(VIOLATIONS_CSV)
payments_df = pd.read_csv(PAYMENTS_CSV)
drivers_df = pd.read_csv(DRIVERS_CSV)

print(f"✓ Loaded:")
print(f"   violations: {len(violations_df)} rows from {VIOLATIONS_CSV.name}")
print(f"   payments: {len(payments_df)} rows from {PAYMENTS_CSV.name}")
print(f"   drivers: {len(drivers_df)} rows from {DRIVERS_CSV.name}")

# ----------------------------------------------------------------------
# Step 1b: Column Mapping & Validation
# ----------------------------------------------------------------------
COLUMN_ALIASES = {
    # Violations
    'offence_type': 'offense_type',
    'offense_type': 'offense_type',
    'speed_limit': 'posted_speed_limit',
    'weather': 'weather_conditions',
    'weather_condition': 'weather_conditions',
    'violation_date_time': 'occurred_at',
    'violation_date': 'occurred_at',
    'occurred_date': 'occurred_at',
    # Payments
    'payment_id': 'payment_id',
    'violation_id': 'violation_id',
    'driver_id': 'driver_id',
    'amount': 'amount',
    'amount_paid': 'amount_paid',
    'status': 'status',
    'paid_at': 'paid_at',
    'is_synthetic': 'is_synthetic',
    # Drivers
    'driver_id': 'driver_id',
    'license_issue_date': 'license_issue_date',
    'license_issue': 'license_issue_date',
    'is_synthetic': 'is_synthetic',
}


def apply_column_mapping(df, entity_name):
    """Rename columns using aliases, validate required columns exist."""
    df = df.copy()
    rename_map = {}
    # Entity-specific ID mapping (all CSVs use generic 'id' column)
    id_map = {
        'violations': 'violation_id',
        'payments': 'payment_id',
        'drivers': 'driver_id',
    }
    if 'id' in df.columns and entity_name in id_map:
        rename_map['id'] = id_map[entity_name]
    for col in df.columns:
        col_clean = col.strip().lower()
        if col_clean in COLUMN_ALIASES:
            rename_map[col] = COLUMN_ALIASES[col_clean]
        elif col_clean in [v.lower() for v in COLUMN_ALIASES.values()]:
            pass
    df = df.rename(columns=rename_map)

    required_cols = {
        'violations': ['violation_id', 'driver_id', 'offense_type', 'speed_recorded',
                      'posted_speed_limit', 'zone_type', 'weather_conditions',
                      'points_assigned', 'occurred_at', 'is_synthetic'],
        'payments': ['payment_id', 'violation_id', 'driver_id', 'amount',
                    'amount_paid', 'status', 'paid_at', 'is_synthetic'],
        'drivers': ['driver_id', 'license_issue_date', 'is_synthetic'],
    }

    missing = [c for c in required_cols[entity_name] if c not in df.columns]
    if missing:
        raise ValueError(
            f"❌ {entity_name} is missing required columns: {missing}\n"
            f"   Available columns: {list(df.columns)}\n"
        )
    return df


violations_df = apply_column_mapping(violations_df, 'violations')
payments_df = apply_column_mapping(payments_df, 'payments')
drivers_df = apply_column_mapping(drivers_df, 'drivers')

print("✓ Column mapping applied successfully")
print(f"   Violations columns: {list(violations_df.columns)}")
print(f"   Payments columns: {list(payments_df.columns)}")
print(f"   Drivers columns: {list(drivers_df.columns)}")

# ----------------------------------------------------------------------
# Step 2: Data Readiness & EDA
# ----------------------------------------------------------------------
print("\n📊 Parsing dates and validating data...")
print("=" * 60)

violations_df['occurred_at'] = pd.to_datetime(violations_df['occurred_at'], errors='coerce')
payments_df['paid_at'] = pd.to_datetime(payments_df['paid_at'], errors='coerce')
drivers_df['license_issue_date'] = pd.to_datetime(drivers_df['license_issue_date'], errors='coerce')

bad_violation_dates = violations_df['occurred_at'].isna().sum()
bad_payment_dates = payments_df['paid_at'].isna().sum()
bad_driver_dates = drivers_df['license_issue_date'].isna().sum()

if bad_violation_dates > 0:
    print(f"⚠️  {bad_violation_dates} violation rows had unparseable occurred_at and will be dropped.")
    violations_df = violations_df.dropna(subset=['occurred_at'])

if bad_payment_dates > 0:
    print(f"⚠️  {bad_payment_dates} payment rows had unparseable paid_at.")

if bad_driver_dates > 0:
    print(f"⚠️  {bad_driver_dates} driver rows had unparseable license_issue_date.")

if not INCLUDE_SYNTHETIC:
    before = len(violations_df)
    violations_df = violations_df[violations_df['is_synthetic'] == 0]
    after = len(violations_df)
    print(f"\n🔍 Filtered synthetic rows: {before} → {after} ({before - after} removed)")
    if after < 200:
        print(f"\n❌ ERROR: Only {after} real (non-synthetic) violations remain.")
        raise SystemExit("Insufficient real data for training.")

first_date = violations_df['occurred_at'].min()
last_date = violations_df['occurred_at'].max()

total_drivers = drivers_df['driver_id'].nunique()
total_violations = len(violations_df)
total_payments = len(payments_df)
drivers_with_violations = violations_df['driver_id'].nunique()
violations_per_driver = violations_df.groupby('driver_id').size()

print("\n📈 DATA READINESS REPORT")
print("=" * 60)
print(f"Date range: {first_date.date()} to {last_date.date()}")
print(f"Total drivers: {total_drivers}")
print(f"Drivers with violations: {drivers_with_violations}")
print(f"Total violations: {total_violations}")
print(f"Total payments: {total_payments}")
print(f"Violations per driver: Min={violations_per_driver.min()}, Median={violations_per_driver.median():.1f}, Max={violations_per_driver.max()}, Mean={violations_per_driver.mean():.1f}")

drivers_multi = (violations_per_driver >= 2).sum()
print(f"Drivers with 2+ violations: {drivers_multi}")
if drivers_multi < 100:
    print("   ⚠️  WARNING: Fewer than 100 drivers have 2+ violations.")

print("\n✓ Data readiness check complete.")

# ----------------------------------------------------------------------
# Step 2 (cont): Feature Engineering
# ----------------------------------------------------------------------
print("\n🔧 Building per-driver features at each violation's occurred_at...")
print("=" * 60)

violations_df = violations_df.sort_values('occurred_at').reset_index(drop=True)
driver_violations = violations_df.groupby('driver_id')
payments_df['paid_at'] = pd.to_datetime(payments_df['paid_at'], errors='coerce')

feature_rows = []

for idx, v in violations_df.iterrows():
    driver_id = v['driver_id']
    occurred_at = v['occurred_at']

    driver_v = driver_violations.get_group(driver_id)
    driver_v_past = driver_v[driver_v['occurred_at'] <= occurred_at]
    driver_v_prior_90d = driver_v_past[driver_v_past['occurred_at'] >= occurred_at - timedelta(days=90)]
    driver_v_prior_180d_before = driver_v_past[
        (driver_v_past['occurred_at'] >= occurred_at - timedelta(days=180)) &
        (driver_v_past['occurred_at'] < occurred_at - timedelta(days=90))
    ]

    violation_count_12m = len(driver_v_past[driver_v_past['occurred_at'] >= occurred_at - timedelta(days=365)])
    violation_count_90d = len(driver_v_prior_90d)

    weighted_points_12m = 0.0
    severe_count = 0
    severe_offence_types = {'dui', 'reckless_driving', 'red_light', 'driving_without_license'}

    for _, dv in driver_v_past[driver_v_past['occurred_at'] >= occurred_at - timedelta(days=365)].iterrows():
        offense = dv['offense_type']
        weight = OFFENSE_POINTS.get(offense, 1)
        weighted_points_12m += weight * float(dv['points_assigned'])
        if offense in severe_offence_types:
            severe_count += 1

    severe_offence_share = severe_count / violation_count_12m if violation_count_12m > 0 else 0.0

    cumulative_points = driver_v_past['points_assigned'].sum()

    speeding = driver_v_past[
        (driver_v_past['offense_type'] == 'speeding') &
        (driver_v_past['speed_recorded'].notna()) &
        (driver_v_past['posted_speed_limit'].notna())
    ]
    avg_speed_over_limit = 0.0
    max_speed_over_limit = 0.0
    if len(speeding) > 0:
        excesses = (speeding['speed_recorded'].astype(float) - speeding['posted_speed_limit'].astype(float)).clip(lower=0)
        avg_speed_over_limit = excesses.mean()
        max_speed_over_limit = excesses.max()

    if len(driver_v_past) > 1:
        days_since_last = (occurred_at - driver_v_past.iloc[-2]['occurred_at']).days
    else:
        days_since_last = 365

    if len(driver_v_past) >= 2:
        sorted_dv = driver_v_past.sort_values('occurred_at')
        gaps = []
        for i in range(1, len(sorted_dv)):
            gaps.append((sorted_dv.iloc[i]['occurred_at'] - sorted_dv.iloc[i - 1]['occurred_at']).days)
        mean_gap = np.mean(gaps) if gaps else 0.0
    else:
        mean_gap = 0.0

    if len(driver_v_prior_180d_before) > 0:
        trend = (violation_count_90d - len(driver_v_prior_180d_before)) / len(driver_v_prior_180d_before)
    elif violation_count_90d > 0:
        trend = 1.0
    else:
        trend = 0.0

    share_night = (driver_v_past['weather_conditions'] == 'night').mean() if len(driver_v_past) > 0 else 0.0
    share_school_zone = (driver_v_past['zone_type'] == 'school_zone').mean() if len(driver_v_past) > 0 else 0.0
    share_bad_weather = driver_v_past['weather_conditions'].isin(['rain', 'fog', 'storm']).mean() if len(driver_v_past) > 0 else 0.0

    driver_payments = payments_df[payments_df['driver_id'] == driver_id]
    driver_paid = driver_payments[driver_payments['status'] == 'completed']

    total_fines = driver_v_past['points_assigned'].sum() * FINE_PER_POINT
    paid_fines = 0.0
    payment_delays = []

    for _, dv in driver_v_past.iterrows():
        dv_payments = driver_paid[driver_paid['violation_id'] == dv['violation_id']]
        fine = dv['points_assigned'] * FINE_PER_POINT
        paid_fines += fine
        for _, p in dv_payments.iterrows():
            if pd.notna(p['paid_at']) and pd.notna(dv['occurred_at']):
                delay = (p['paid_at'] - dv['occurred_at']).days
                payment_delays.append(delay)

    unpaid_fine_ratio = max(0.0, min(1.0, 1.0 - (paid_fines / total_fines))) if total_fines > 0 else 0.0
    avg_payment_delay = np.mean(payment_delays) if payment_delays else 0.0

    appeals_lost = 0
    new_driver = violation_count_12m < 2

    driver_future = driver_v[driver_v['occurred_at'] > occurred_at]
    driver_future_within_180 = driver_future[driver_future['occurred_at'] <= occurred_at + timedelta(days=LABEL_HORIZON_DAYS)]
    reoffended = 1 if len(driver_future_within_180) > 0 else 0

    feature_rows.append({
        'driver_id': driver_id,
        'violation_id': v['violation_id'],
        'occurred_at': occurred_at,
        'days_since_last_offense': days_since_last,
        'speed_recorded': float(v['speed_recorded']) if pd.notna(v['speed_recorded']) else 0.0,
        'posted_speed_limit': float(v['posted_speed_limit']) if pd.notna(v['posted_speed_limit']) else 80.0,
        'zone_type': v['zone_type'],
        'weather_conditions': v['weather_conditions'],
        'cumulative_active_demerit_points': int(cumulative_points),
        'past_fine_payment_delays_days': round(float(avg_payment_delay), 2),
        'violation_count_12m': violation_count_12m,
        'violation_count_90d': violation_count_90d,
        'weighted_points_12m': round(weighted_points_12m, 2),
        'severe_offence_share': round(severe_offence_share, 4),
        'avg_speed_over_limit': round(avg_speed_over_limit, 2),
        'max_speed_over_limit': round(max_speed_over_limit, 2),
        'mean_gap_between_offences': round(mean_gap, 2),
        'trend': round(trend, 4),
        'share_night': round(share_night, 4),
        'share_school_zone': round(share_school_zone, 4),
        'share_bad_weather': round(share_bad_weather, 4),
        'unpaid_fine_ratio': round(unpaid_fine_ratio, 4),
        'avg_payment_delay_days': round(avg_payment_delay, 2),
        'appeals_lost': appeals_lost,
        'new_driver': int(new_driver),
        'reoffended_within_180_days': reoffended,
        'is_synthetic': int(v['is_synthetic']) if 'is_synthetic' in v and pd.notna(v['is_synthetic']) else 0,
    })

features_df = pd.DataFrame(feature_rows)
print(f"\n✓ Built {len(features_df)} feature rows (one per violation)")
print(f"   Features per row: {len(features_df.columns)}")

label_dist = features_df['reoffended_within_180_days'].value_counts()
print(f"\nLabel distribution (reoffended_within_{LABEL_HORIZON_DAYS}d):")
for k, v in label_dist.items():
    print(f"   Class {k}: {v} ({v / len(features_df) * 100:.1f}%)")

if label_dist.get(1, 0) < 100:
    print(f"\n⚠️  WARNING: Only {label_dist.get(1, 0)} positive (reoffended) labels.")

print("\n✓ Feature engineering complete.")

# ----------------------------------------------------------------------
# Step 3: Time-Based Train/Test Split
# ----------------------------------------------------------------------
print("\n📅 Computing time-based split...")
print("=" * 60)

first_date = violations_df['occurred_at'].min()
last_date = violations_df['occurred_at'].max()

first_usable_cutoff = first_date + timedelta(days=90)
last_valid_cutoff = last_date - timedelta(days=LABEL_HORIZON_DAYS)

if last_valid_cutoff <= first_usable_cutoff:
    print("❌ ERROR: Not enough data span.")
    raise SystemExit("Insufficient date range for time-based split.")

total_span = (last_valid_cutoff - first_usable_cutoff).days
split_offset = int(0.75 * total_span)
SPLIT_DATE = first_usable_cutoff + timedelta(days=split_offset)

print(f"First date in data: {first_date.date()}")
print(f"Last date in data: {last_date.date()}")
print(f"First usable cutoff: {first_usable_cutoff.date()}")
print(f"Last valid cutoff: {last_valid_cutoff.date()}")
print(f"SPLIT_DATE (75% of span): {SPLIT_DATE.date()}")

cutoffs = []
current = first_usable_cutoff
while current <= last_valid_cutoff:
    cutoffs.append(current)
    current += timedelta(days=CUTOFF_EVERY_DAYS)

if len(cutoffs) < 5:
    CUTOFF_EVERY_DAYS = max(7, CUTOFF_EVERY_DAYS // 2)
    cutoffs = []
    current = first_usable_cutoff
    while current <= last_valid_cutoff:
        cutoffs.append(current)
        current += timedelta(days=CUTOFF_EVERY_DAYS)
    print(f"\n⚠️  Halved spacing to {CUTOFF_EVERY_DAYS} days → {len(cutoffs)} cutoffs")

train_rows = []
test_rows = []

for _, row in features_df.iterrows():
    occurred_at = row['occurred_at']
    if occurred_at <= SPLIT_DATE - timedelta(days=LABEL_HORIZON_DAYS):
        train_rows.append(row)
    elif occurred_at >= SPLIT_DATE:
        test_rows.append(row)

train_df = pd.DataFrame(train_rows)
test_df = pd.DataFrame(test_rows)

print(f"\nTraining rows: {len(train_df)}")
print(f"Test rows: {len(test_df)}")

if len(train_df) < MIN_TRAIN_ROWS:
    print(f"\n⚠️  WARNING: Only {len(train_df)} training rows (minimum {MIN_TRAIN_ROWS} recommended).")

print(f"\nTrain label distribution:\n{train_df['reoffended_within_180_days'].value_counts()}")
print(f"\nTest label distribution:\n{test_df['reoffended_within_180_days'].value_counts()}")

train_pos_rate = train_df['reoffended_within_180_days'].mean() if len(train_df) > 0 else 0
test_pos_rate = test_df['reoffended_within_180_days'].mean() if len(test_df) > 0 else 0

if train_pos_rate < 0.05 or train_pos_rate > 0.95:
    print(f"\n⚠️  WARNING: Train positive rate ({train_pos_rate:.1%}) is outside 5%-95% range.")

print("\n✓ Train/test split complete.")

# ----------------------------------------------------------------------
# Step 4: Supervised Risk Model Training
# ----------------------------------------------------------------------
print("\n🤖 Building supervised risk model...")
print("=" * 60)

ORIGINAL_6 = [
    'days_since_last_offense',
    'speed_recorded',
    'zone_type',
    'weather_conditions',
    'cumulative_active_demerit_points',
    'past_fine_payment_delays_days',
]

SEGMENT_FEATURES = [
    'violation_count_12m',
    'violation_count_90d',
    'weighted_points_12m',
    'severe_offence_share',
    'avg_speed_over_limit',
    'max_speed_over_limit',
    'mean_gap_between_offences',
    'trend',
    'share_night',
    'share_school_zone',
    'share_bad_weather',
    'unpaid_fine_ratio',
    'avg_payment_delay_days',
    'new_driver',
]

RULE_FEATURE = 'appeals_lost'

feature_sets = {
    'A_original_6': ORIGINAL_6,
    'B_plus_segmentation': ORIGINAL_6 + SEGMENT_FEATURES,
    'C_plus_rules': ORIGINAL_6 + [RULE_FEATURE],
    'D_plus_both': ORIGINAL_6 + SEGMENT_FEATURES + [RULE_FEATURE],
}

RANDOM_STATE = 42
N_SPLITS = min(5, max(2, len(train_df) // 40))


def build_pipeline(feature_cols, numeric_cols, categorical_cols, model_name):
    preprocess = ColumnTransformer(
        transformers=[
            (
                'num',
                Pipeline([('imputer', SimpleImputer(strategy='median'))]),
                numeric_cols,
            ),
            (
                'cat',
                Pipeline([
                    ('imputer', SimpleImputer(strategy='most_frequent')),
                    ('onehot', OneHotEncoder(handle_unknown='ignore')),
                ]),
                categorical_cols,
            ),
        ]
    )
    if model_name == 'random_forest':
        model = RandomForestClassifier(
            n_estimators=300, max_depth=10, min_samples_leaf=3,
            random_state=RANDOM_STATE, n_jobs=-1, class_weight='balanced',
        )
    elif model_name == 'gradient_boosting':
        model = GradientBoostingClassifier(
            n_estimators=200, max_depth=5, random_state=RANDOM_STATE,
        )
    elif model_name == 'logistic_regression':
        model = LogisticRegression(
            max_iter=2000, random_state=RANDOM_STATE, class_weight='balanced',
        )
    else:
        raise ValueError(f'Unknown model: {model_name}')
    return Pipeline([('preprocess', preprocess), ('clf', model)])


# Ablation study
ablation_results = []

for fs_name, fs_cols in feature_sets.items():
    print(f"\n--- Feature Set: {fs_name} ---")
    avail_cols = [c for c in fs_cols if c in train_df.columns]
    missing = [c for c in fs_cols if c not in train_df.columns]
    if missing:
        print(f"   Missing columns: {missing}")

    X_train = train_df[avail_cols].copy()
    y_train = train_df['reoffended_within_180_days'].astype(int)
    X_test = test_df[avail_cols].copy()
    y_test = test_df['reoffended_within_180_days'].astype(int)

    numeric_cols = [c for c in avail_cols if c not in ['zone_type', 'weather_conditions']]
    categorical_cols = [c for c in avail_cols if c in ['zone_type', 'weather_conditions']]

    for model_name in ['random_forest', 'gradient_boosting', 'logistic_regression']:
        fold_scores = {'roc_auc': [], 'f1': [], 'precision': [], 'recall': []}
        cv = StratifiedKFold(
            n_splits=min(N_SPLITS, len(train_df) // 20 + 1),
            shuffle=True, random_state=RANDOM_STATE,
        )
        for train_idx, test_idx in cv.split(X_train, y_train):
            X_tr, X_te = X_train.iloc[train_idx], X_train.iloc[test_idx]
            y_tr, y_te = y_train.iloc[train_idx], y_train.iloc[test_idx]
            pipe = build_pipeline(avail_cols, numeric_cols, categorical_cols, model_name)
            pipe.fit(X_tr, y_tr)
            y_proba = pipe.predict_proba(X_te)[:, 1]
            y_pred = (y_proba >= 0.5).astype(int)
            fold_scores['roc_auc'].append(roc_auc_score(y_te, y_proba))
            fold_scores['f1'].append(f1_score(y_te, y_pred, zero_division=0))
            fold_scores['precision'].append(precision_score(y_te, y_pred, zero_division=0))
            fold_scores['recall'].append(recall_score(y_te, y_pred, zero_division=0))

        mean_auc = np.mean(fold_scores['roc_auc'])
        std_auc = np.std(fold_scores['roc_auc'])
        mean_f1 = np.mean(fold_scores['f1'])

        ablation_results.append({
            'feature_set': fs_name,
            'model': model_name,
            'roc_auc_mean': round(float(mean_auc), 4),
            'roc_auc_std': round(float(std_auc), 4),
            'f1_mean': round(float(mean_f1), 4),
            'precision_mean': round(float(np.mean(fold_scores['precision'])), 4),
            'recall_mean': round(float(np.mean(fold_scores['recall'])), 4),
        })
        print(f"   {model_name}: ROC-AUC={mean_auc:.4f} (±{std_auc:.4f}), F1={mean_f1:.4f}")

ablation_df = pd.DataFrame(ablation_results)
ablation_df.to_json(REPORTS_DIR / "ablation_report.json", orient="records", indent=2)

best_row = ablation_df.loc[ablation_df['roc_auc_mean'].idxmax()]
best_fs = best_row['feature_set']
best_model = best_row['model']

fs_map = {
    'A_original_6': ORIGINAL_6,
    'B_plus_segmentation': ORIGINAL_6 + SEGMENT_FEATURES,
    'C_plus_rules': ORIGINAL_6 + [RULE_FEATURE],
    'D_plus_both': ORIGINAL_6 + SEGMENT_FEATURES + [RULE_FEATURE],
}
best_features = fs_map.get(best_fs, ORIGINAL_6)

print(f"\n✓ Best feature set: {best_fs}")
print(f"✓ Best model: {best_model}")
print(f"✓ Best ROC-AUC: {best_row['roc_auc_mean']:.4f}")

# Train final calibrated model
print(f"\n🤖 Training final calibrated model ({best_model} + {best_fs})...")

X_train = train_df[best_features].copy()
y_train = train_df['reoffended_within_180_days'].astype(int)
X_test = test_df[best_features].copy()
y_test = test_df['reoffended_within_180_days'].astype(int)

numeric_cols = [c for c in best_features if c not in ['zone_type', 'weather_conditions']]
categorical_cols = [c for c in best_features if c in ['zone_type', 'weather_conditions']]

pipe = build_pipeline(best_features, numeric_cols, categorical_cols, best_model)
calibrated = CalibratedClassifierCV(pipe, method='isotonic', cv=3)
calibrated.fit(X_train, y_train)

y_proba = calibrated.predict_proba(X_test)[:, 1]
y_pred = (y_proba >= 0.5).astype(int)

auc = roc_auc_score(y_test, y_proba)
f1 = f1_score(y_test, y_pred, zero_division=0)
precision = precision_score(y_test, y_pred, zero_division=0)
recall = recall_score(y_test, y_pred, zero_division=0)
brier = brier_score_loss(y_test, y_proba)
pr_auc = average_precision_score(y_test, y_proba)

print(f"\nTest ROC-AUC: {auc:.4f}")
print(f"Test PR-AUC: {pr_auc:.4f}")
print(f"Test Brier: {brier:.4f}")
print(f"Test F1: {f1:.4f}")
print(f"Test Precision: {precision:.4f}")
print(f"Test Recall: {recall:.4f}")

# Compute per-tier precision/recall/F1
# Sanction tiers based on score thresholds
def score_to_tier(score):
    if score >= RISK_SANCTION_THRESHOLD:
        return 'suspended'
    elif score >= RISK_TRAINING_THRESHOLD:
        return 'restricted'
    elif score >= RISK_WARNING_THRESHOLD:
        return 'warning'
    else:
        return 'good_standing'

test_tiers = [score_to_tier(s) for s in y_proba]
tier_metrics = {}
for tier in ['good_standing', 'warning', 'restricted', 'suspended']:
    tier_mask = np.array(test_tiers) == tier
    if tier_mask.sum() == 0:
        tier_metrics[tier] = {'n': 0, 'precision': 0.0, 'recall': 0.0, 'f1': 0.0}
        continue
    y_tier_true = y_test[tier_mask]
    y_tier_pred = y_pred[tier_mask]
    tier_metrics[tier] = {
        'n': int(tier_mask.sum()),
        'precision': round(float(precision_score(y_tier_true, y_tier_pred, zero_division=0)), 4),
        'recall': round(float(recall_score(y_tier_true, y_tier_pred, zero_division=0)), 4),
        'f1': round(float(f1_score(y_tier_true, y_tier_pred, zero_division=0)), 4),
    }

# Save model as risk_model_v3.joblib
model_version = "rf_v3"
model_artifact = {
    'model': calibrated,
    'feature_cols': best_features,
    'numeric_cols': numeric_cols,
    'categorical_cols': categorical_cols,
    'model_version': model_version,
    'trained_at': datetime.now().isoformat(),
    'data_source': DATA_SOURCE,
    'include_synthetic': INCLUDE_SYNTHETIC,
    'label_horizon_days': LABEL_HORIZON_DAYS,
    'sklearn_version': __import__('sklearn').__version__,
    'metrics': {
        'roc_auc': round(float(auc), 4),
        'pr_auc': round(float(pr_auc), 4),
        'brier': round(float(brier), 4),
        'f1': round(float(f1), 4),
        'precision': round(float(precision), 4),
        'recall': round(float(recall), 4),
        'tier_metrics': tier_metrics,
    },
    'points_floor_anchors': POINTS_FLOOR_ANCHORS,
    'sanction_thresholds': {
        'warning': RISK_WARNING_THRESHOLD,
        'training': RISK_TRAINING_THRESHOLD,
        'suspension': RISK_SANCTION_THRESHOLD,
    },
}

model_path = MODEL_DIR / "risk_model_v3.joblib"
joblib.dump(model_artifact, model_path)
print(f"\n✓ Model saved to {model_path}")

# Save model_meta.json
meta = {
    'model_version': model_version,
    'feature_cols': best_features,
    'numeric_cols': numeric_cols,
    'categorical_cols': categorical_cols,
    'known_categories': {
        'zone_type': sorted(train_df['zone_type'].dropna().unique().tolist()),
        'weather_conditions': sorted(train_df['weather_conditions'].dropna().unique().tolist()),
    },
    'thresholds': {
        'warning': RISK_WARNING_THRESHOLD,
        'training': RISK_TRAINING_THRESHOLD,
        'suspension': RISK_SANCTION_THRESHOLD,
    },
    'points_floor_anchors': POINTS_FLOOR_ANCHORS,
    'metrics': model_artifact['metrics'],
    'sklearn_version': __import__('sklearn').__version__,
    'trained_at': model_artifact['trained_at'],
    'data_source': DATA_SOURCE,
    'include_synthetic': INCLUDE_SYNTHETIC,
    'label_horizon_days': LABEL_HORIZON_DAYS,
    'n_train': int(len(train_df)),
    'n_test': int(len(test_df)),
}
meta_path = REPORTS_DIR / "model_meta.json"
with open(meta_path, 'w') as f:
    json.dump(meta, f, indent=2)
print(f"✓ Model metadata saved to {meta_path}")

# Save heuristic_v2.json
# Compute feature means/scales for heuristic_v2
feature_means = {}
feature_scales = {}
for col in best_features:
    if col in numeric_cols:
        feature_means[col] = round(float(train_df[col].mean()), 4)
        feature_scales[col] = round(float(train_df[col].std()), 4)
    else:
        feature_means[col] = train_df[col].mode().iloc[0] if len(train_df[col].mode()) > 0 else ''
        feature_scales[col] = 1.0

# Coefficients for heuristic_v2 (non-negative logistic)
coefficients = {
    'cumulative_active_demerit_points': 0.015,
    'violation_count_12m': 0.03,
    'violation_count_90d': 0.05,
    'weighted_points_12m': 0.01,
    'severe_offence_share': 0.20,
    'avg_speed_over_limit': 0.005,
    'max_speed_over_limit': 0.003,
    'days_since_last_offense': -0.001,
    'mean_gap_between_offences': -0.001,
    'trend': 0.10,
    'share_night': 0.05,
    'share_school_zone': 0.05,
    'share_bad_weather': 0.05,
    'unpaid_fine_ratio': 0.15,
    'avg_payment_delay_days': 0.002,
    'appeals_lost': 0.05,
    'new_driver': -0.05,
    'speed_recorded': 0.002,
    'past_fine_payment_delays_days': 0.001,
}
intercept = 0.05

heuristic_v2 = {
    'intercept': intercept,
    'coefficients': coefficients,
    'feature_means': feature_means,
    'feature_scales': feature_scales,
    'numeric_cols': numeric_cols,
    'categorical_cols': categorical_cols,
    'model_version': 'heuristic_v2',
    'description': 'Deterministic logistic fallback when ML service is down. All coefficients non-negative.',
}
heuristic_path = REPORTS_DIR / "heuristic_v2.json"
with open(heuristic_path, 'w') as f:
    json.dump(heuristic_v2, f, indent=2)
print(f"✓ Heuristic v2 saved to {heuristic_path}")

# Save fairness report
print("\n⚖️ Fairness Report")
print("=" * 60)

X_train_full = train_df[best_features].copy()
y_train_full = train_df['reoffended_within_180_days'].astype(int)
y_train_proba = calibrated.predict_proba(X_train_full)[:, 1]
y_train_pred = (y_train_proba >= 0.5).astype(int)

train_df['y_pred'] = y_train_pred
train_df['y_true'] = y_train_full

overall_fpr = ((train_df['y_pred'] == 1) & (train_df['y_true'] == 0)).sum() / max((train_df['y_true'] == 0).sum(), 1)
print(f"Overall FPR: {overall_fpr:.4f}")

fairness = {'overall_fpr': round(float(overall_fpr), 4)}

for group_col in ['zone_type', 'weather_conditions']:
    if group_col not in train_df.columns:
        continue
    group_fairness = {}
    for val in train_df[group_col].unique():
        subset = train_df[train_df[group_col] == val]
        if len(subset) == 0:
            continue
        fpr = ((subset['y_pred'] == 1) & (subset['y_true'] == 0)).sum() / max((subset['y_true'] == 0).sum(), 1)
        tpr = ((subset['y_pred'] == 1) & (subset['y_true'] == 1)).sum() / max((subset['y_true'] == 1).sum(), 1)
        flagged = fpr > overall_fpr * 1.25
        group_fairness[str(val)] = {
            'fpr': round(float(fpr), 4),
            'tpr': round(float(tpr), 4),
            'n': int(len(subset)),
            'fpr_ratio_to_overall': round(float(fpr / max(overall_fpr, 0.001)), 4),
            'flagged': bool(flagged),
        }
    fairness[group_col] = group_fairness
    flagged_groups = [k for k, v in group_fairness.items() if v['flagged']]
    if flagged_groups:
        print(f"  {group_col}: FLAGGED groups: {flagged_groups}")
    else:
        print(f"  {group_col}: No groups flagged")

fairness_path = REPORTS_DIR / "fairness_report.json"
with open(fairness_path, 'w') as f:
    json.dump(fairness, f, indent=2)
print(f"\n✓ Fairness report saved to {fairness_path}")

# Create artifacts zip
output_dir = Path(__file__).resolve().parent / "artifacts_output"
output_dir.mkdir(parents=True, exist_ok=True)

shutil.copy2(model_path, output_dir / 'risk_model_v3.joblib')
shutil.copy2(meta_path, output_dir / 'model_meta.json')
shutil.copy2(heuristic_path, output_dir / 'heuristic_v2.json')
shutil.copy2(fairness_path, output_dir / 'fairness_report.json')
shutil.copy2(REPORTS_DIR / "ablation_report.json", output_dir / 'ablation_report.json')

zip_path = Path(__file__).resolve().parent / "risk_model_artifacts.zip"
with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zf:
    for p in output_dir.rglob('*'):
        if p.is_file():
            zf.write(p, p.name)

print(f"\n✓ Artifacts zipped to: {zip_path}")
print(f"   Size: {zip_path.stat().st_size / 1024:.1f} KB")

print("\n📋 NEXT STEPS:")
print(f"1. Model: {model_path}")
print(f"2. Metadata: {meta_path}")
print(f"3. Heuristic: {heuristic_path}")
print(f"4. Fairness: {fairness_path}")
print(f"5. Copy risk_model_v3.joblib to ml_service/model/ (already there)")
print(f"6. Copy model_meta.json, heuristic_v2.json, fairness_report.json to ml_service/reports/")
print(f"7. Restart ML service: python -m uvicorn app:app --host 127.0.0.1 --port 8001")
