"""
k-Means driver segmentation (unsupervised).

Follows the course notes algorithm:
  1. Choose k -> 2. Initialise random centroids -> 3. Assign each point to
     nearest centroid by Euclidean distance -> 4. Recompute centroids as
     cluster means -> 5. Repeat until assignments stop changing ->
     6. Keep run with lowest total within-cluster variation.

Uses scikit-learn KMeans with:
  - n_init >= 20 (repeat with different random starts, keep lowest variation)
  - fixed random_state
  - StandardScaler first (Euclidean distance is scale-sensitive)

Clustering features (NO demographics):
  violation_count, cumulative_active_demerit_points, avg_speed_over_limit,
  past_fine_payment_delays_days, unpaid_fine_ratio, days_since_last_offense,
  share_at_night

Finds k via elbow plot (WCSS k=1..10) + kneedle, cross-checked with
silhouette scores for k=2..10.

Persists to segment_models and driver_segments tables.
"""

from __future__ import annotations

import json
import os
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
import pymysql
from dotenv import load_dotenv
from sklearn.cluster import KMeans
from sklearn.decomposition import PCA
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import silhouette_score
from kneed import KneeLocator
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt

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

MODEL_DIR = Path(__file__).resolve().parent.parent / "model"
MODEL_DIR.mkdir(parents=True, exist_ok=True)

PLOTS_DIR = Path(__file__).resolve().parent.parent / "reports" / "eda"
PLOTS_DIR.mkdir(parents=True, exist_ok=True)

CLUSTERING_FEATURES = [
    "violation_count",
    "cumulative_active_demerit_points",
    "avg_speed_over_limit",
    "past_fine_payment_delays_days",
    "unpaid_fine_ratio",
    "days_since_last_offense",
    "share_at_night",
]

RANDOM_STATE = 42
N_INIT = 25  # >= 20 per course notes


def get_connection():
    return pymysql.connect(**DB_CONFIG)


def load_features():
    """Load per-driver features from ml_driver_features table."""
    conn = get_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT * FROM ml_driver_features")
            rows = cur.fetchall()
    finally:
        conn.close()
    return pd.DataFrame(rows)


def label_segment(centroid_row: pd.Series) -> str:
    """Auto-generate a readable segment label from centroid profile."""
    vc = centroid_row["violation_count"]
    points = centroid_row["cumulative_active_demerit_points"]
    speed = centroid_row["avg_speed_over_limit"]
    delays = centroid_row["past_fine_payment_delays_days"]
    unpaid = centroid_row["unpaid_fine_ratio"]
    nights = centroid_row["share_at_night"]
    recency = centroid_row["days_since_last_offense"]

    # Rule-based labeling in code (not hand-typed)
    if vc < 2:
        return "New / insufficient history"
    if points > 20 and unpaid > 0.5:
        return "Chronic late payers"
    if speed > 20 and nights > 0.4:
        return "Habitual night-time speeders"
    if speed > 15:
        return "Habitual speeders"
    if nights > 0.5:
        return "Night-time offenders"
    if points > 10:
        return "High-risk repeat offenders"
    if vc >= 2 and points <= 5 and recency > 90:
        return "Low-risk compliant"
    return f"Segment {int(centroid_row.name)}"


def train_kmeans():
    print("=" * 60)
    print("k-Means Driver Segmentation")
    print("=" * 60)

    df = load_features()
    print(f"\nLoaded {len(df)} driver feature records")

    # Use only drivers with complete clustering features
    feature_df = df[CLUSTERING_FEATURES].copy()
    feature_df = feature_df.fillna(feature_df.median(numeric_only=True))

    # Standardize
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(feature_df)

    # 2-component PCA projection of the scaled feature matrix (for scatter plots)
    pca_coords = PCA(n_components=2).fit_transform(X_scaled)

    # ---------------------------------------------------------------
    # Find k: WCSS for k=1..10 + kneedle elbow + silhouette cross-check
    # ---------------------------------------------------------------
    print("\nComputing WCSS for k=1..10...")
    wcss = {}
    silhouettes = {}
    models = {}

    for k in range(1, 11):
        km = KMeans(
            n_clusters=k,
            n_init=N_INIT,
            random_state=RANDOM_STATE,
            max_iter=300,
        )
        km.fit(X_scaled)
        wcss[k] = km.inertia_
        models[k] = km
        if k >= 2:
            sil = silhouette_score(X_scaled, km.labels_)
            silhouettes[k] = sil
            print(f"  k={k}: WCSS={km.inertia_:.2f}, Silhouette={sil:.4f}")
        else:
            print(f"  k={k}: WCSS={km.inertia_:.2f}")

    # Elbow detection with kneedle
    kl = KneeLocator(
        list(wcss.keys()),
        list(wcss.values()),
        curve="convex",
        direction="decreasing",
    )
    elbow_k = kl.elbow if kl.elbow else 3
    print(f"\nKneedle elbow: k={elbow_k}")

    # Silhouette cross-check
    if silhouettes:
        best_sil_k = max(silhouettes, key=silhouettes.get)
        print(f"Silhouette best: k={best_sil_k} (score={silhouettes[best_sil_k]:.4f})")

    # If they disagree, choose silhouette maximum among candidates near elbow
    if best_sil_k != elbow_k and silhouettes:
        # Consider k values within 1 of elbow
        candidates = [k for k in silhouettes if abs(k - elbow_k) <= 1]
        if candidates:
            chosen_k = max(candidates, key=lambda k: silhouettes[k])
            print(f"Elbow ({elbow_k}) and silhouette ({best_sil_k}) disagree.")
            print(f"Choosing silhouette maximum near elbow: k={chosen_k}")
        else:
            chosen_k = elbow_k
    else:
        chosen_k = elbow_k

    print(f"\nSelected k = {chosen_k}")

    # Save elbow plot
    plt.figure(figsize=(8, 5))
    ks = list(wcss.keys())
    wcss_vals = [wcss[k] for k in ks]
    plt.plot(ks, wcss_vals, "bo-", linewidth=2, markersize=8)
    plt.axvline(x=chosen_k, color="r", linestyle="--", label=f"Chosen k={chosen_k}")
    plt.xlabel("k")
    plt.ylabel("WCSS (Inertia)")
    plt.title("Elbow Plot: WCSS vs k")
    plt.legend()
    plt.grid(True, alpha=0.3)
    plt.tight_layout()
    plt.savefig(PLOTS_DIR / "elbow_plot.png", dpi=100)
    plt.close()

    # Save silhouette plot
    if silhouettes:
        plt.figure(figsize=(8, 5))
        sil_ks = list(silhouettes.keys())
        sil_vals = [silhouettes[k] for k in sil_ks]
        plt.plot(sil_ks, sil_vals, "go-", linewidth=2, markersize=8)
        plt.axvline(x=chosen_k, color="r", linestyle="--", label=f"Chosen k={chosen_k}")
        plt.xlabel("k")
        plt.ylabel("Silhouette Score")
        plt.title("Silhouette Score vs k")
        plt.legend()
        plt.grid(True, alpha=0.3)
        plt.tight_layout()
        plt.savefig(PLOTS_DIR / "silhouette_plot.png", dpi=100)
        plt.close()

    # ---------------------------------------------------------------
    # Train final model with chosen k
    # ---------------------------------------------------------------
    print(f"\nTraining final KMeans with k={chosen_k}...")
    final_km = KMeans(
        n_clusters=chosen_k,
        n_init=N_INIT,
        random_state=RANDOM_STATE,
        max_iter=300,
    )
    final_km.fit(X_scaled)
    labels = final_km.labels_

    # Compute silhouette for final model
    final_silhouette = silhouette_score(X_scaled, labels) if chosen_k >= 2 else None
    print(f"  Final WCSS: {final_km.inertia_:.2f}")
    print(f"  Final Silhouette: {final_silhouette:.4f}" if final_silhouette else "  N/A (k=1)")

    # ---------------------------------------------------------------
    # Centroids in original units (inverse transform)
    # ---------------------------------------------------------------
    centroids_scaled = final_km.cluster_centers_
    centroids_original = scaler.inverse_transform(centroids_scaled)
    centroids_df = pd.DataFrame(centroids_original, columns=CLUSTERING_FEATURES)

    # Auto-generate labels (ensure uniqueness)
    labels_generated = [label_segment(r) for _, r in centroids_df.iterrows()]
    # De-duplicate: append cluster number if same label appears more than once
    from collections import Counter
    label_counts = Counter(labels_generated)
    seen = {}
    unique_labels = []
    for lbl in labels_generated:
        if label_counts[lbl] > 1:
            seen[lbl] = seen.get(lbl, 0) + 1
            unique_labels.append(f"{lbl} #{seen[lbl]}")
        else:
            unique_labels.append(lbl)
    centroids_df["segment_label"] = unique_labels
    print("\nSegment labels:")
    for i, row in centroids_df.iterrows():
        print(f"  Cluster {i}: {row['segment_label']}")

    # ---------------------------------------------------------------
    # Assign every driver to nearest centroid
    # ---------------------------------------------------------------
    # Distance to each centroid (in scaled space)
    distances = final_km.transform(X_scaled)  # shape: (n_samples, k)
    nearest_centroid = distances.argmin(axis=1)
    min_distance = distances.min(axis=1)

    # Flag atypical: distance > mean + 2*std of all min distances
    dist_mean = min_distance.mean()
    dist_std = min_distance.std()
    atypical_threshold = dist_mean + 2 * dist_std
    is_atypical = min_distance > atypical_threshold

    print(f"\nAtypical threshold: distance > {atypical_threshold:.4f}")
    print(f"  Atypical drivers: {int(is_atypical.sum())}")

    # ---------------------------------------------------------------
    # Persist to segment_models
    # ---------------------------------------------------------------
    model_version = f"kmeans_v1"
    elbow_data = {str(k): float(wcss[k]) for k in wcss}
    wcss_by_k_json = json.dumps({str(k): float(wcss[k]) for k in sorted(wcss)})
    silhouette_by_k_json = json.dumps(
        {str(k): float(silhouettes[k]) for k in sorted(silhouettes)}
    )

    scaler_data = {
        "mean": scaler.mean_.tolist(),
        "scale": scaler.scale_.tolist(),
        "features": CLUSTERING_FEATURES,
    }

    conn = get_connection()
    try:
        with conn.cursor() as cur:
            # Deactivate old models (insert new, old ones remain but superseded)
            cur.execute(
                """
                INSERT INTO segment_models
                (model_version, k, centroids, scaler, wcss, silhouette, elbow_data, wcss_by_k, silhouette_by_k, trained_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    k = VALUES(k),
                    centroids = VALUES(centroids),
                    scaler = VALUES(scaler),
                    wcss = VALUES(wcss),
                    silhouette = VALUES(silhouette),
                    elbow_data = VALUES(elbow_data),
                    wcss_by_k = VALUES(wcss_by_k),
                    silhouette_by_k = VALUES(silhouette_by_k),
                    trained_at = NOW(),
                    updated_at = NOW()
                """,
                (
                    model_version,
                    chosen_k,
                    json.dumps(centroids_original.tolist()),
                    json.dumps(scaler_data),
                    float(final_km.inertia_),
                    float(final_silhouette) if final_silhouette else None,
                    json.dumps(elbow_data),
                    wcss_by_k_json,
                    silhouette_by_k_json,
                ),
            )

            # Update existing assignments (UNIQUE key on driver_profile_id;
            # upsert only — rows are never deleted)

            # Insert new assignments
            for idx, (_, driver_row) in enumerate(df.iterrows()):
                did = int(driver_row["driver_profile_id"])
                seg_id = int(labels[idx])
                seg_label = centroids_df.iloc[seg_id]["segment_label"]
                distance = float(min_distance[idx])
                atypical = bool(is_atypical[idx])

                cur.execute(
                    """
                    INSERT INTO driver_segments
                    (driver_profile_id, segment_id, segment_label, distance_to_centroid,
                     model_version, is_atypical, pca_x, pca_y, assigned_at, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        segment_id = VALUES(segment_id),
                        segment_label = VALUES(segment_label),
                        distance_to_centroid = VALUES(distance_to_centroid),
                        model_version = VALUES(model_version),
                        is_atypical = VALUES(is_atypical),
                        pca_x = VALUES(pca_x),
                        pca_y = VALUES(pca_y),
                        assigned_at = NOW(),
                        updated_at = NOW()
                """,
                    (
                        did,
                        seg_id,
                        seg_label,
                        distance,
                        model_version,
                        atypical,
                        float(pca_coords[idx][0]),
                        float(pca_coords[idx][1]),
                    ),
                )

        conn.commit()
        print(f"\nPersisted {len(df)} driver segment assignments")
        print("k-Means segmentation complete!")
    finally:
        conn.close()

    # Save scaler and model artifacts
    joblib.dump(scaler, MODEL_DIR / "kmeans_scaler.joblib")
    joblib.dump(final_km, MODEL_DIR / "kmeans_model.joblib")
    print(f"Model artifacts saved to {MODEL_DIR}")


if __name__ == "__main__":
    train_kmeans()
