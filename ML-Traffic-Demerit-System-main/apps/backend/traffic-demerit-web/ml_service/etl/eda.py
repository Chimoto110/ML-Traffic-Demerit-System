"""
EDA: Exploratory Data Analysis for ML driver features.

Generates:
- missing_values_report.json
- distributions (histogram PNGs per numeric column)
- outliers (boxplot PNGs per numeric column)
- correlation_heatmap.png
- class_balance.json
- eda_summary.md

All outputs saved to ml_service/reports/eda/
"""

from __future__ import annotations

import os
import json
from pathlib import Path

import pymysql
import pandas as pd
import numpy as np
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import seaborn as sns

from dotenv import load_dotenv

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

REPORTS_DIR = Path(__file__).resolve().parent.parent / "reports" / "eda"
REPORTS_DIR.mkdir(parents=True, exist_ok=True)

NUMERIC_COLS = [
    "violation_count",
    "cumulative_active_demerit_points",
    "days_since_last_offense",
    "avg_speed_over_limit",
    "share_in_school_zone",
    "share_at_night",
    "total_fines_kes",
    "past_fine_payment_delays_days",
    "unpaid_fine_ratio",
    "appeals_filed",
]


def get_connection():
    return pymysql.connect(**DB_CONFIG)


def load_data():
    conn = get_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT * FROM ml_driver_features")
            rows = cur.fetchall()
    finally:
        conn.close()
    return pd.DataFrame(rows)


def missing_values_report(df: pd.DataFrame):
    missing = df.isnull().sum()
    missing_pct = (df.isnull().mean() * 100).round(2)
    report = {
        col: {"missing_count": int(missing[col]), "missing_percent": float(missing_pct[col])}
        for col in df.columns
    }
    with open(REPORTS_DIR / "missing_values_report.json", "w") as f:
        json.dump(report, f, indent=2)
    print(f"  missing_values_report.json: {sum(int(v['missing_count']) for v in report.values())} total missing")


def distribution_plots(df: pd.DataFrame):
    for col in NUMERIC_COLS:
        if col not in df.columns:
            continue
        plt.figure(figsize=(8, 5))
        sns.histplot(df[col].dropna(), bins=30, kde=True)
        plt.title(f"Distribution of {col}")
        plt.xlabel(col)
        plt.ylabel("Count")
        plt.tight_layout()
        plt.savefig(REPORTS_DIR / f"dist_{col}.png", dpi=100)
        plt.close()
    print(f"  distribution plots: {len(NUMERIC_COLS)} PNGs")


def outlier_plots(df: pd.DataFrame):
    for col in NUMERIC_COLS:
        if col not in df.columns:
            continue
        plt.figure(figsize=(8, 4))
        sns.boxplot(x=df[col].dropna())
        plt.title(f"Boxplot: {col} (outliers)")
        plt.xlabel(col)
        plt.tight_layout()
        plt.savefig(REPORTS_DIR / f"outliers_{col}.png", dpi=100)
        plt.close()
    print(f"  outlier plots: {len(NUMERIC_COLS)} PNGs")


def correlation_heatmap(df: pd.DataFrame):
    corr_cols = [c for c in NUMERIC_COLS if c in df.columns] + ["reoffended_within_180_days"]
    corr = df[corr_cols].corr()
    plt.figure(figsize=(10, 8))
    sns.heatmap(corr, annot=True, fmt=".2f", cmap="coolwarm", center=0)
    plt.title("Feature Correlation Heatmap")
    plt.tight_layout()
    plt.savefig(REPORTS_DIR / "correlation_heatmap.png", dpi=100)
    plt.close()
    print("  correlation_heatmap.png saved")


def class_balance(df: pd.DataFrame):
    if "reoffended_within_180_days" not in df.columns:
        return
    counts = df["reoffended_within_180_days"].value_counts().to_dict()
    total = len(df)
    report = {
        "total": int(total),
        "class_0_count": int(counts.get(0, 0)),
        "class_1_count": int(counts.get(1, 0)),
        "class_0_pct": round(float(counts.get(0, 0)) / total * 100, 2),
        "class_1_pct": round(float(counts.get(1, 0)) / total * 100, 2),
    }
    with open(REPORTS_DIR / "class_balance.json", "w") as f:
        json.dump(report, f, indent=2)
    print(f"  class_balance.json: {report}")


def summary_markdown(df: pd.DataFrame):
    lines = []
    lines.append("# EDA Summary")
    lines.append("")
    lines.append(f"Total driver records: {len(df)}")
    lines.append(f"Synthetic records: {int(df['is_synthetic'].sum()) if 'is_synthetic' in df.columns else 0}")
    lines.append(f"Real (original) records: {int((~df['is_synthetic'].astype(bool)).sum()) if 'is_synthetic' in df.columns else 0}")
    lines.append("")

    lines.append("## Class Balance (reoffended_within_180_days)")
    if "reoffended_within_180_days" in df.columns:
        cb = df["reoffended_within_180_days"].value_counts()
        for k, v in cb.items():
            lines.append(f"- Class {k}: {v} ({v/len(df)*100:.1f}%)")
    lines.append("")

    lines.append("## Numeric Feature Summary")
    lines.append(df[NUMERIC_COLS].describe().to_markdown())
    lines.append("")

    lines.append("## Data Limitations")
    lines.append("- All feature data is derived from synthetic driver profiles (is_synthetic=1 for generated rows).")
    lines.append("- Original rows (is_synthetic=0) retain real-world provenance but are few (50 drivers).")
    lines.append("- The `reoffended_within_180_days` label is derived from violation history: a driver is labeled")
    lines.append("  as reoffended if they have a second violation within 180 days of their first.")
    lines.append("- This labeling approach has limitations: drivers with only one violation cannot be labeled as")
    lines.append("  reoffended, and the 180-day window may not capture all recidivism patterns.")
    lines.append("- Results on this data demonstrate the pipeline and say nothing about real-world accuracy.")

    with open(REPORTS_DIR / "eda_summary.md", "w") as f:
        f.write("\n".join(lines))
    print("  eda_summary.md saved")


def main():
    print("=" * 60)
    print("EDA: Exploratory Data Analysis")
    print("=" * 60)

    print("\n[1/4] Loading data from MySQL...")
    df = load_data()
    print(f"  Loaded {len(df)} driver feature records")

    print("\n[2/4] Missing values report...")
    missing_values_report(df)

    print("\n[3/4] Generating distribution and outlier plots...")
    distribution_plots(df)
    outlier_plots(df)

    print("\n[4/4] Correlation heatmap, class balance, summary...")
    correlation_heatmap(df)
    class_balance(df)
    summary_markdown(df)

    print(f"\nEDA complete! Reports saved to {REPORTS_DIR}")


if __name__ == "__main__":
    main()
