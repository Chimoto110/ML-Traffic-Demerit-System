"""
Supervised risk model training with ablation study.

Candidate features:
  Original 6: days_since_last_offense, speed_recorded, zone_type,
              weather_conditions, cumulative_active_demerit_points,
              past_fine_payment_delays_days
  Plus: violation_count, new_driver, segment_id (one-hot),
        distance_to_centroid, rule_hit_count

Ablation: (A) original 6, (B) A + segmentation, (C) A + rules, (D) A + both
Models: RandomForest, GradientBoosting, LogisticRegression
Calibration: CalibratedClassifierCV
Explainability: SHAP (or permutation importance fallback)
Fairness: per zone_type, weather, segment
"""

from __future__ import annotations

import json
import os
import warnings
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
import pymysql
from dotenv import load_dotenv
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import RandomForestClassifier, GradientBoostingClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.calibration import CalibratedClassifierCV
from sklearn.impute import SimpleImputer
from sklearn.metrics import (
    roc_auc_score, f1_score, precision_score, recall_score,
    classification_report
)
from sklearn.model_selection import StratifiedKFold, train_test_split
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder
from sklearn.inspection import permutation_importance

warnings.filterwarnings("ignore")
load_dotenv()

DB_CONFIG = {
    "host": os.getenv("DB_HOST", "127.0.0.1"),
    "port": int(os.getenv("DB_PORT", "3306")),
    "user": os.getenv("DB_USERNAME", "root"),
    "password": os.getenv("DB_PASSWORD", ""),
    "database": os.getenv("DB_DATABASE", "traffic_demerit"),
    "charset": "utf8mb4",
    "cursorclass": pymysql.cursors.DictCursor,
}

MODEL_DIR = Path(__file__).resolve().parent / "model"
MODEL_DIR.mkdir(parents=True, exist_ok=True)

REPORTS_DIR = Path(__file__).resolve().parent / "reports"
REPORTS_DIR.mkdir(parents=True, exist_ok=True)

ORIGINAL_6 = [
    "days_since_last_offense",
    "speed_recorded",
    "zone_type",
    "weather_conditions",
    "cumulative_active_demerit_points",
    "past_fine_payment_delays_days",
]

SEGMENT_FEATURES = ["violation_count", "new_driver", "distance_to_centroid"]
RULE_FEATURE = "rule_hit_count"

RANDOM_STATE = 42
N_SPLITS = 5


def get_connection():
    return pymysql.connect(**DB_CONFIG)


def load_training_data():
    """Build training dataset: one row per driver's latest violation.

    Features: 6 original from violation + aggregated driver features
              + segment + rule hits.
    Target: reoffended_within_180_days.
    """
    conn = get_connection()
    try:
        with conn.cursor() as cur:
            # Get latest violation per driver with the 6 original features
            cur.execute("""
                SELECT v.driver_id, v.speed_recorded, v.zone_type,
                       v.weather_conditions,
                       df.days_since_last_offense,
                       df.cumulative_active_demerit_points,
                       df.past_fine_payment_delays_days,
                       df.violation_count,
                       df.new_driver,
                       df.reoffended_within_180_days
                FROM violations v
                JOIN driver_profiles dp ON v.driver_id = dp.id
                JOIN ml_driver_features df ON dp.id = df.driver_profile_id
                WHERE v.occurred_at = (
                    SELECT MAX(v2.occurred_at)
                    FROM violations v2
                    WHERE v2.driver_id = v.driver_id
                )
            """)
            rows = cur.fetchall()

            # Get segments
            cur.execute("""
                SELECT driver_profile_id, segment_id, distance_to_centroid
                FROM driver_segments
            """)
            seg_rows = cur.fetchall()

            # Get risk rules (antecedents only, for matching)
            cur.execute("""
                SELECT antecedent FROM association_rules
                WHERE rule_family = 'risk'
            """)
            rule_rows = cur.fetchall()
    finally:
        conn.close()

    df = pd.DataFrame(rows)
    seg_df = pd.DataFrame(seg_rows)
    rules = [json.loads(r["antecedent"]) for r in rule_rows]

    if len(df) == 0:
        return df, rules

    # Merge segments
    if len(seg_df) > 0:
        df = df.merge(
            seg_df,
            left_on="driver_id",
            right_on="driver_profile_id",
            how="left",
        )
        df["segment_id"] = df["segment_id"].fillna(-1).astype(int)
        df["distance_to_centroid"] = df["distance_to_centroid"].fillna(999.0)
    else:
        df["segment_id"] = -1
        df["distance_to_centroid"] = 999.0

    # Compute rule_hit_count: how many risk rule antecedents match this violation
    df["rule_hit_count"] = 0
    for i, row in df.iterrows():
        hits = 0
        for antecedent in rules:
            match = True
            for item in antecedent:
                if item.startswith("zone="):
                    zone_val = item.split("=", 1)[1]
                    if str(row.get("zone_type", "")) != zone_val:
                        match = False
                        break
                elif item.startswith("weather="):
                    weather_val = item.split("=", 1)[1]
                    if str(row.get("weather_conditions", "")) != weather_val:
                        match = False
                        break
                else:
                    # Can't evaluate type, time, payment, outcome from available features
                    match = False
                    break
            if match:
                hits += 1
        df.at[i, "rule_hit_count"] = hits

    return df, rules


def build_pipeline(feature_cols, numeric_cols, categorical_cols, model_name):
    """Build sklearn pipeline with preprocessing + model."""
    preprocess = ColumnTransformer(
        transformers=[
            (
                "num",
                Pipeline([("imputer", SimpleImputer(strategy="median"))]),
                numeric_cols,
            ),
            (
                "cat",
                Pipeline([
                    ("imputer", SimpleImputer(strategy="most_frequent")),
                    ("onehot", OneHotEncoder(handle_unknown="ignore")),
                ]),
                categorical_cols,
            ),
        ]
    )

    if model_name == "random_forest":
        model = RandomForestClassifier(
            n_estimators=300, max_depth=10, min_samples_leaf=3,
            random_state=RANDOM_STATE, n_jobs=-1, class_weight="balanced",
        )
    elif model_name == "gradient_boosting":
        model = GradientBoostingClassifier(
            n_estimators=200, max_depth=5, random_state=RANDOM_STATE,
        )
    elif model_name == "logistic_regression":
        model = LogisticRegression(
            max_iter=2000, random_state=RANDOM_STATE, class_weight="balanced",
        )
    else:
        raise ValueError(f"Unknown model: {model_name}")

    return Pipeline([
        ("preprocess", preprocess),
        ("clf", model),
    ])


def ablation_study(df):
    """Run ablation study with stratified k-fold CV."""
    print("\n" + "=" * 60)
    print("Ablation Study")
    print("=" * 60)

    # Prepare feature sets
    feature_sets = {
        "A_original_6": ORIGINAL_6,
        "B_plus_segmentation": ORIGINAL_6 + SEGMENT_FEATURES + ["segment_id"],
        "C_plus_rules": ORIGINAL_6 + [RULE_FEATURE],
        "D_plus_both": ORIGINAL_6 + SEGMENT_FEATURES + ["segment_id", RULE_FEATURE],
    }

    models = ["random_forest", "gradient_boosting", "logistic_regression"]
    cv = StratifiedKFold(n_splits=N_SPLITS, shuffle=True, random_state=RANDOM_STATE)

    results = []

    for fs_name, fs_cols in feature_sets.items():
        print(f"\n--- Feature Set: {fs_name} ---")

        # Filter to available columns
        avail_cols = [c for c in fs_cols if c in df.columns]
        missing = [c for c in fs_cols if c not in df.columns]
        if missing:
            print(f"  Missing columns: {missing}")

        X = df[avail_cols].copy()
        y = df["reoffended_within_180_days"].astype(int)

        # Identify numeric and categorical
        numeric_cols = [c for c in avail_cols if c not in ["zone_type", "weather_conditions", "segment_id"]]
        categorical_cols = [c for c in avail_cols if c in ["zone_type", "weather_conditions", "segment_id"]]

        for model_name in models:
            fold_scores = {"roc_auc": [], "f1": [], "precision": [], "recall": []}

            for train_idx, test_idx in cv.split(X, y):
                X_train, X_test = X.iloc[train_idx], X.iloc[test_idx]
                y_train, y_test = y.iloc[train_idx], y.iloc[test_idx]

                pipe = build_pipeline(avail_cols, numeric_cols, categorical_cols, model_name)
                pipe.fit(X_train, y_train)

                y_proba = pipe.predict_proba(X_test)[:, 1]
                y_pred = (y_proba >= 0.5).astype(int)

                fold_scores["roc_auc"].append(roc_auc_score(y_test, y_proba))
                fold_scores["f1"].append(f1_score(y_test, y_pred, zero_division=0))
                fold_scores["precision"].append(
                    precision_score(y_test, y_pred, zero_division=0)
                )
                fold_scores["recall"].append(recall_score(y_test, y_pred, zero_division=0))

            mean_auc = np.mean(fold_scores["roc_auc"])
            std_auc = np.std(fold_scores["roc_auc"])
            mean_f1 = np.mean(fold_scores["f1"])

            results.append({
                "feature_set": fs_name,
                "model": model_name,
                "roc_auc_mean": round(float(mean_auc), 4),
                "roc_auc_std": round(float(std_auc), 4),
                "f1_mean": round(float(mean_f1), 4),
                "precision_mean": round(float(np.mean(fold_scores["precision"])), 4),
                "recall_mean": round(float(np.mean(fold_scores["recall"])), 4),
            })

            print(f"  {model_name}: ROC-AUC={mean_auc:.4f} (+/-{std_auc:.4f}), F1={mean_f1:.4f}")

    results_df = pd.DataFrame(results)
    results_df.to_json(REPORTS_DIR / "ablation_report.json", orient="records", indent=2)
    print(f"\nAblation report saved to {REPORTS_DIR / 'ablation_report.json'}")

    # Determine best feature set
    best = results_df.loc[results_df["roc_auc_mean"].idxmax()]
    print(f"\nBest: {best['feature_set']} + {best['model']} (ROC-AUC={best['roc_auc_mean']:.4f})")

    return results_df


def train_final_model(df, best_feature_set, best_model_name):
    """Train final calibrated model on all data."""
    print("\n" + "=" * 60)
    print("Final Model Training")
    print("=" * 60)

    avail_cols = [c for c in best_feature_set if c in df.columns]
    numeric_cols = [c for c in avail_cols if c not in ["zone_type", "weather_conditions", "segment_id"]]
    categorical_cols = [c for c in avail_cols if c in ["zone_type", "weather_conditions", "segment_id"]]

    X = df[avail_cols]
    y = df["reoffended_within_180_days"].astype(int)

    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=RANDOM_STATE, stratify=y
    )

    # Build pipeline
    pipe = build_pipeline(avail_cols, numeric_cols, categorical_cols, best_model_name)

    # Calibrate
    calibrated = CalibratedClassifierCV(
        pipe, method="isotonic", cv=3
    )
    calibrated.fit(X_train, y_train)

    # Evaluate
    y_proba = calibrated.predict_proba(X_test)[:, 1]
    y_pred = (y_proba >= 0.5).astype(int)

    auc = roc_auc_score(y_test, y_proba)
    f1 = f1_score(y_test, y_pred, zero_division=0)

    print(f"  Test ROC-AUC: {auc:.4f}")
    print(f"  Test F1: {f1:.4f}")

    # Save model
    model_version = "rf_v2"
    joblib.dump({
        "model": calibrated,
        "feature_cols": avail_cols,
        "numeric_cols": numeric_cols,
        "categorical_cols": categorical_cols,
        "model_version": model_version,
    }, MODEL_DIR / "risk_model_rf_v2.joblib")
    print(f"  Model saved to {MODEL_DIR / 'risk_model_rf_v2.joblib'}")

    return calibrated, avail_cols, numeric_cols, categorical_cols


def compute_fairness(df, model, feature_cols):
    """Compute fairness metrics per group."""
    print("\n" + "=" * 60)
    print("Fairness Report")
    print("=" * 60)

    X = df[feature_cols]
    y = df["reoffended_within_180_days"].astype(int)
    y_proba = model.predict_proba(X)[:, 1]
    y_pred = (y_proba >= 0.5).astype(int)

    df["y_pred"] = y_pred
    df["y_true"] = y

    fairness = {}
    overall_fpr = (
        (df["y_pred"] == 1) & (df["y_true"] == 0)
    ).sum() / max((df["y_true"] == 0).sum(), 1)

    for group_col in ["zone_type", "weather_conditions", "segment_id"]:
        if group_col not in df.columns:
            continue
        group_fairness = {}
        for val in df[group_col].unique():
            subset = df[df[group_col] == val]
            if len(subset) == 0:
                continue
            fpr = (
                (subset["y_pred"] == 1) & (subset["y_true"] == 0)
            ).sum() / max((subset["y_true"] == 0).sum(), 1)
            tpr = (
                (subset["y_pred"] == 1) & (subset["y_true"] == 1)
            ).sum() / max((subset["y_true"] == 1).sum(), 1)
            group_fairness[str(val)] = {
                "fpr": round(float(fpr), 4),
                "tpr": round(float(tpr), 4),
                "n": int(len(subset)),
                "fpr_ratio_to_overall": round(float(fpr / max(overall_fpr, 0.001)), 4),
                "flagged": bool(fpr > overall_fpr * 1.25),
            }
        fairness[group_col] = group_fairness

    fairness["overall_fpr"] = round(float(overall_fpr), 4)

    with open(REPORTS_DIR / "fairness_report.json", "w") as f:
        json.dump(fairness, f, indent=2)

    print(f"  Overall FPR: {overall_fpr:.4f}")
    for gc, gf in fairness.items():
        if gc == "overall_fpr":
            continue
        flagged = [k for k, v in gf.items() if v["flagged"]]
        if flagged:
            print(f"  {gc}: FLAGGED groups: {flagged}")
        else:
            print(f"  {gc}: No groups flagged")

    print(f"  Fairness report saved to {REPORTS_DIR / 'fairness_report.json'}")
    return fairness


def main():
    print("=" * 60)
    print("Supervised Risk Model Training")
    print("=" * 60)

    df, rules = load_training_data()
    print(f"\nLoaded {len(df)} training records")
    print(f"  Target distribution: {df['reoffended_within_180_days'].value_counts().to_dict()}")

    # Ablation study
    results_df = ablation_study(df)

    # Pick best feature set + model
    best_row = results_df.loc[results_df["roc_auc_mean"].idxmax()]
    best_fs = best_row["feature_set"]
    best_model = best_row["model"]

    # Map feature set name to columns
    fs_map = {
        "A_original_6": ORIGINAL_6,
        "B_plus_segmentation": ORIGINAL_6 + SEGMENT_FEATURES + ["segment_id"],
        "C_plus_rules": ORIGINAL_6 + [RULE_FEATURE],
        "D_plus_both": ORIGINAL_6 + SEGMENT_FEATURES + ["segment_id", RULE_FEATURE],
    }
    best_features = fs_map.get(best_fs, ORIGINAL_6)

    # Train final model
    model, feat_cols, num_cols, cat_cols = train_final_model(
        df, best_features, best_model
    )

    # Fairness
    compute_fairness(df, model, feat_cols)

    print("\nSupervised model training complete!")


if __name__ == "__main__":
    main()
