from __future__ import annotations

import argparse
from pathlib import Path

import joblib
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import RandomForestClassifier
from sklearn.impute import SimpleImputer
from sklearn.metrics import classification_report, f1_score, roc_auc_score
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

FEATURE_COLUMNS = [
    "days_since_last_offense",
    "speed_recorded",
    "zone_type",
    "weather_conditions",
    "cumulative_active_demerit_points",
    "past_fine_payment_delays_days",
]


# Target is binary: 1 means expected to reoffend in evaluation horizon.
TARGET_COLUMN = "reoffended_within_180_days"


def build_pipeline(random_state: int) -> Pipeline:
    numeric_features = [
        "days_since_last_offense",
        "speed_recorded",
        "cumulative_active_demerit_points",
        "past_fine_payment_delays_days",
    ]
    categorical_features = ["zone_type", "weather_conditions"]

    preprocess = ColumnTransformer(
        transformers=[
            (
                "num",
                Pipeline(
                    steps=[
                        ("imputer", SimpleImputer(strategy="median")),
                    ]
                ),
                numeric_features,
            ),
            (
                "cat",
                Pipeline(
                    steps=[
                        ("imputer", SimpleImputer(strategy="most_frequent")),
                        ("onehot", OneHotEncoder(handle_unknown="ignore")),
                    ]
                ),
                categorical_features,
            ),
        ]
    )

    clf = RandomForestClassifier(
        n_estimators=300,
        max_depth=10,
        min_samples_leaf=3,
        random_state=random_state,
        n_jobs=-1,
        class_weight="balanced",
    )

    return Pipeline(
        steps=[
            ("preprocess", preprocess),
            ("clf", clf),
        ]
    )


def train(input_csv: Path, output_dir: Path, random_state: int):
    df = pd.read_csv(input_csv)

    missing = [col for col in FEATURE_COLUMNS + [TARGET_COLUMN] if col not in df.columns]
    if missing:
        raise ValueError(f"Dataset missing required columns: {missing}")

    X = df[FEATURE_COLUMNS].copy()
    y = df[TARGET_COLUMN].astype(int)

    X_train, X_test, y_train, y_test = train_test_split(
        X,
        y,
        test_size=0.2,
        random_state=random_state,
        stratify=y,
    )

    pipeline = build_pipeline(random_state=random_state)
    pipeline.fit(X_train, y_train)

    probabilities = pipeline.predict_proba(X_test)[:, 1]
    predictions = (probabilities >= 0.5).astype(int)

    roc_auc = roc_auc_score(y_test, probabilities)
    f1 = f1_score(y_test, predictions)

    print("=== Evaluation Metrics ===")
    print(f"ROC-AUC: {roc_auc:.4f}")
    print(f"F1-score: {f1:.4f}")
    print(classification_report(y_test, predictions, digits=4))

    output_dir.mkdir(parents=True, exist_ok=True)
    model_path = output_dir / "random_forest_pipeline.joblib"
    metadata_path = output_dir / "metadata.joblib"

    joblib.dump(pipeline, model_path)
    joblib.dump(
        {
            "model_version": "rf_v1",
            "features": FEATURE_COLUMNS,
            "target": TARGET_COLUMN,
            "algorithm": "RandomForestClassifier",
        },
        metadata_path,
    )

    print(f"Saved model to {model_path}")
    print(f"Saved metadata to {metadata_path}")


def main():
    parser = argparse.ArgumentParser(description="Train RandomForest recidivism model")
    parser.add_argument("--input", required=True, help="Path to simulated/anonymized CSV dataset")
    parser.add_argument(
        "--output-dir",
        default=str(Path(__file__).resolve().parent / "model"),
        help="Directory where model artifacts will be saved",
    )
    parser.add_argument("--seed", type=int, default=42)
    args = parser.parse_args()

    train(Path(args.input), Path(args.output_dir), args.seed)


if __name__ == "__main__":
    main()
