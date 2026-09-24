from __future__ import annotations

import os
import time
from pathlib import Path
from typing import Dict, List

import joblib
import numpy as np
import pandas as pd
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

MODEL_PATH = Path(__file__).resolve().parent / "model" / "random_forest_pipeline.joblib"
METADATA_PATH = Path(__file__).resolve().parent / "model" / "metadata.joblib"

LOW_CUTOFF = float(os.getenv("RISK_LOW_CUTOFF", "0.40"))
HIGH_CUTOFF = float(os.getenv("RISK_HIGH_CUTOFF", "0.70"))

app = FastAPI(title="Traffic Demerit Random Forest Service", version="1.0.0")


class PredictionPayload(BaseModel):
    days_since_last_offense: float = Field(ge=0)
    speed_recorded: float = Field(ge=0)
    zone_type: str
    weather_conditions: str
    cumulative_active_demerit_points: float = Field(ge=0)
    past_fine_payment_delays_days: float = Field(ge=0)


class PredictionResult(BaseModel):
    engine: str
    model_version: str
    driver_risk_score: float
    confidence_level: float
    risk_class: str
    inference_latency_ms: float
    explanation_top_factors: List[Dict[str, float | str]]


def load_artifacts():
    if not MODEL_PATH.exists() or not METADATA_PATH.exists():
        raise RuntimeError(
            "Model artifacts are missing. Run train_model.py using a simulated or anonymized dataset first."
        )

    pipeline = joblib.load(MODEL_PATH)
    metadata = joblib.load(METADATA_PATH)
    return pipeline, metadata


def classify(score: float) -> str:
    if score >= HIGH_CUTOFF:
        return "high"
    if score >= LOW_CUTOFF:
        return "moderate"
    return "low"


def factor_explanations(pipeline, features: pd.DataFrame, top_n: int = 3):
    preprocess = pipeline.named_steps["preprocess"]
    clf = pipeline.named_steps["clf"]

    transformed = preprocess.transform(features)
    if hasattr(transformed, "toarray"):
        transformed = transformed.toarray()

    values = np.abs(transformed[0])
    importances = clf.feature_importances_
    names = preprocess.get_feature_names_out()

    scores = values * importances
    ranked = np.argsort(scores)[::-1]

    results: List[Dict[str, float | str]] = []
    for idx in ranked:
        contribution = float(scores[idx])
        if contribution <= 0:
            continue

        results.append(
            {
                "feature": str(names[idx]),
                "contribution": round(contribution, 6),
            }
        )

        if len(results) >= top_n:
            break

    return results


@app.get("/health")
def health():
    return {"status": "ok", "engine": "RandomForestClassifier"}


@app.post("/predict", response_model=PredictionResult)
def predict(payload: PredictionPayload):
    try:
        pipeline, metadata = load_artifacts()
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc

    # Only ethically approved non-demographic features are used.
    df = pd.DataFrame(
        [
            {
                "days_since_last_offense": payload.days_since_last_offense,
                "speed_recorded": payload.speed_recorded,
                "zone_type": payload.zone_type,
                "weather_conditions": payload.weather_conditions,
                "cumulative_active_demerit_points": payload.cumulative_active_demerit_points,
                "past_fine_payment_delays_days": payload.past_fine_payment_delays_days,
            }
        ]
    )

    try:
        started = time.perf_counter()
        probability = float(pipeline.predict_proba(df)[0][1])
        latency_ms = (time.perf_counter() - started) * 1000
    except Exception as exc:  # pragma: no cover - defensive API boundary
        raise HTTPException(status_code=400, detail=f"Inference failed: {exc}") from exc

    factors = factor_explanations(pipeline, df)
    confidence = max(probability, 1 - probability)

    return {
        "engine": "RandomForestClassifier",
        "model_version": str(metadata.get("model_version", "rf_v1")),
        "driver_risk_score": round(probability, 6),
        "confidence_level": round(confidence, 6),
        "risk_class": classify(probability),
        "inference_latency_ms": round(float(latency_ms), 3),
        "explanation_top_factors": factors,
    }
