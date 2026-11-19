"""Evaluate the deployed risk model against a labelled CSV.

Examples
--------
    python evaluate_model.py
    python evaluate_model.py --input data/simulated_reoffending.csv --threshold 0.5

The CSV must contain the six model features plus the label column
(default ``reoffended_within_180_days``). Metrics are printed and written
to ``reports/model_evaluation.json`` so a report can quote them directly.
"""

from __future__ import annotations

import argparse
import json
from datetime import datetime, timezone
from pathlib import Path

import joblib
import numpy as np
import pandas as pd

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "model" / "risk_model_v3.joblib"
META_PATH = BASE_DIR / "model" / "model_meta.json"
REPORTS_DIR = BASE_DIR / "reports"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--input",
        default=str(BASE_DIR / "data" / "simulated_reoffending.csv"),
        help="Labelled CSV to score (default: data/simulated_reoffending.csv)",
    )
    parser.add_argument(
        "--label",
        default="reoffended_within_180_days",
        help="Name of the 0/1 label column",
    )
    parser.add_argument(
        "--threshold",
        type=float,
        default=0.5,
        help="Probability threshold used for precision/recall/F1 (default: 0.5)",
    )
    parser.add_argument(
        "--output",
        default=str(REPORTS_DIR / "model_evaluation.json"),
        help="Where to write the JSON results",
    )
    return parser.parse_args()


def load_artifacts() -> tuple[object, dict]:
    if not MODEL_PATH.exists():
        raise SystemExit(f"Model not found: {MODEL_PATH}")
    if not META_PATH.exists():
        raise SystemExit(f"Metadata not found: {META_PATH}")

    artifact = joblib.load(MODEL_PATH)
    model = artifact["model"] if isinstance(artifact, dict) else artifact
    meta = json.loads(META_PATH.read_text(encoding="utf-8"))
    return model, meta


def compute_metrics(y_true: np.ndarray, y_prob: np.ndarray, threshold: float) -> dict:
    from sklearn.metrics import (
        accuracy_score,
        average_precision_score,
        brier_score_loss,
        confusion_matrix,
        f1_score,
        precision_score,
        recall_score,
        roc_auc_score,
    )

    y_pred = (y_prob >= threshold).astype(int)
    matrix = confusion_matrix(y_true, y_pred, labels=[0, 1])

    return {
        "threshold": round(threshold, 4),
        "n_samples": int(len(y_true)),
        "n_positive": int(y_true.sum()),
        "n_negative": int((1 - y_true).sum()),
        "positive_rate": round(float(y_true.mean()), 4),
        "roc_auc": round(float(roc_auc_score(y_true, y_prob)), 4),
        "pr_auc": round(float(average_precision_score(y_true, y_prob)), 4),
        "brier": round(float(brier_score_loss(y_true, y_prob)), 4),
        "accuracy": round(float(accuracy_score(y_true, y_pred)), 4),
        "f1": round(float(f1_score(y_true, y_pred, zero_division=0)), 4),
        "precision": round(float(precision_score(y_true, y_pred, zero_division=0)), 4),
        "recall": round(float(recall_score(y_true, y_pred, zero_division=0)), 4),
        "confusion_matrix": {
            "true_negative": int(matrix[0][0]),
            "false_positive": int(matrix[0][1]),
            "false_negative": int(matrix[1][0]),
            "true_positive": int(matrix[1][1]),
        },
        "score_summary": {
            "min": round(float(y_prob.min()), 4),
            "mean": round(float(y_prob.mean()), 4),
            "max": round(float(y_prob.max()), 4),
        },
    }


def per_category_report(
    df: pd.DataFrame, y_prob: np.ndarray, feature: str, threshold: float
) -> dict:
    """Mean predicted score and positive rate per category."""
    if feature not in df.columns:
        return {}
    frame = pd.DataFrame({"category": df[feature].astype(str), "prob": y_prob})
    grouped = frame.groupby("category")["prob"].agg(["count", "mean"])
    grouped["predicted_positive_rate"] = frame.assign(
        positive=(frame["prob"] >= threshold).astype(int)
    ).groupby("category")["positive"].mean()
    return {
        str(index): {
            "count": int(row["count"]),
            "mean_score": round(float(row["mean"]), 4),
            "predicted_positive_rate": round(float(row["predicted_positive_rate"]), 4),
        }
        for index, row in grouped.iterrows()
    }


def main() -> int:
    args = parse_args()
    model, meta = load_artifacts()

    csv_path = Path(args.input)
    if not csv_path.exists():
        raise SystemExit(f"Input CSV not found: {csv_path}")

    df = pd.read_csv(csv_path)
    feature_cols = meta["feature_cols"]

    missing = [c for c in feature_cols if c not in df.columns]
    if missing:
        raise SystemExit(f"CSV is missing model features: {missing}")
    if args.label not in df.columns:
        raise SystemExit(f"CSV is missing the label column: {args.label}")

    labelled = df.dropna(subset=[args.label]).reset_index(drop=True)
    dropped = len(df) - len(labelled)
    y_true = labelled[args.label].astype(int).to_numpy()
    y_prob = model.predict_proba(labelled[feature_cols])[:, 1]

    metrics = compute_metrics(y_true, y_prob, args.threshold)
    report = {
        "generated_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "model_version": meta.get("model_version"),
        "model_path": str(MODEL_PATH),
        "training_metrics": meta.get("metrics", {}),
        "dataset": {
            "path": str(csv_path),
            "rows_read": int(len(df)),
            "rows_used": int(len(labelled)),
            "rows_dropped": int(dropped),
        },
        "evaluation": metrics,
        "mean_score_by_zone": per_category_report(
            labelled, y_prob, "zone_type", args.threshold
        ),
        "mean_score_by_weather": per_category_report(
            labelled, y_prob, "weather_conditions", args.threshold
        ),
    }

    output_path = Path(args.output)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(json.dumps(report, indent=2), encoding="utf-8")

    e = metrics
    print(f"Model: {report['model_version']}  ({MODEL_PATH.name})")
    print(f"Data:  {csv_path}  ({e['n_samples']} labelled rows, {dropped} dropped)")
    print(f"Label: {args.label}  (positive rate {e['positive_rate']:.2%})")
    print("")
    print(f"  ROC-AUC   {e['roc_auc']:.4f}")
    print(f"  PR-AUC    {e['pr_auc']:.4f}")
    print(f"  Brier     {e['brier']:.4f}")
    print(f"  Accuracy  {e['accuracy']:.4f}  (threshold {e['threshold']:.2f})")
    print(f"  F1        {e['f1']:.4f}")
    print(f"  Precision {e['precision']:.4f}")
    print(f"  Recall    {e['recall']:.4f}")
    print("")
    cm = e["confusion_matrix"]
    print("  Confusion matrix")
    print(f"    TN {cm['true_negative']:6d}   FP {cm['false_positive']:6d}")
    print(f"    FN {cm['false_negative']:6d}   TP {cm['true_positive']:6d}")
    print("")
    print(f"Report written to {output_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
