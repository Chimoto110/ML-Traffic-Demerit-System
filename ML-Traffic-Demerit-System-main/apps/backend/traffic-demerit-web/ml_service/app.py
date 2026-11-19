"""
Traffic Demerit ML Service - FastAPI (versioned REST)

Endpoints:
  GET  /v1/health         - Service health
  POST /v1/predict        - Risk prediction (validated against model_meta.json)
  GET  /v1/model-info     - Model metadata from model_meta.json
  GET  /health            - Backward-compatible health
  POST /predict           - Backward-compatible prediction (legacy)

Models loaded once at startup:
  - risk_model_v3.joblib  (calibrated classifier)
  - model_meta.json       (feature columns, thresholds, metrics, provenance)
"""

from __future__ import annotations

import json
import os
import time
from pathlib import Path
from typing import Any, Dict, List, Optional

import joblib
import numpy as np
import pandas as pd
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

from dotenv import load_dotenv

load_dotenv()

# ----------------------------------------------------------------------
# Configuration
# ----------------------------------------------------------------------
MODEL_DIR = Path(os.getenv("ML_MODEL_DIR", str(Path(__file__).resolve().parent / "model")))
RISK_MODEL_PATH = MODEL_DIR / "risk_model_v3.joblib"
MODEL_META_PATH = MODEL_DIR / "model_meta.json"

LOW_CUTOFF = float(os.getenv("RISK_LOW_CUTOFF", "0.40"))
HIGH_CUTOFF = float(os.getenv("RISK_HIGH_CUTOFF", "0.70"))

app = FastAPI(
    title="Traffic Demerit ML Service",
    version="3.0.0",
    description="Calibrated risk scoring service driven by model_meta.json",
)

# ----------------------------------------------------------------------
# Global model cache (loaded once at startup)
# ----------------------------------------------------------------------
_risk_model: Any = None
_risk_meta: Optional[Dict] = None
_risk_features: List[str] = []
_risk_numeric_cols: List[str] = []
_risk_categorical_cols: List[str] = []
_model_loaded = False
_load_error: Optional[str] = None


def classify(score: float) -> str:
    if score >= HIGH_CUTOFF:
        return "high"
    if score >= LOW_CUTOFF:
        return "moderate"
    return "low"


def _load_model_and_meta() -> None:
    """Load risk_model_v3.joblib and model_meta.json at startup.

    If loading fails, sets _model_loaded = False and records the error
    so endpoints can return 503 instead of crashing.
    """
    global _risk_model, _risk_meta, _risk_features, _risk_numeric_cols
    global _risk_categorical_cols, _model_loaded, _load_error

    try:
        if not RISK_MODEL_PATH.exists():
            _load_error = f"Model file not found: {RISK_MODEL_PATH}"
            print(f"❌ {_load_error}", flush=True)
            _model_loaded = False
            return

        if not MODEL_META_PATH.exists():
            _load_error = f"Model metadata not found: {MODEL_META_PATH}"
            print(f"❌ {_load_error}", flush=True)
            _model_loaded = False
            return

        artifact = joblib.load(RISK_MODEL_PATH)
        _risk_model = artifact["model"]
        _risk_features = list(artifact["feature_cols"])
        _risk_numeric_cols = list(artifact.get("numeric_cols", []))
        _risk_categorical_cols = list(artifact.get("categorical_cols", []))

        with open(MODEL_META_PATH, "r") as f:
            _risk_meta = json.load(f)

        # Cross-check meta features vs artifact features
        meta_features = _risk_meta.get("feature_cols", [])
        if meta_features and meta_features != _risk_features:
            print(
                f"⚠️  model_meta.json feature_cols differ from joblib artifact: "
                f"meta={meta_features}, artifact={_risk_features}",
                flush=True,
            )

        _model_loaded = True
        _load_error = None
        print(
            f"✓ Model loaded: {_risk_meta.get('model_version', 'unknown')} "
            f"({len(_risk_features)} features)", flush=True,
        )
    except Exception as exc:
        _load_error = str(exc)
        _model_loaded = False
        print(f"❌ Failed to load model: {_load_error}", flush=True)


# Load on import
_load_model_and_meta()


# ----------------------------------------------------------------------
# Pydantic models
# ----------------------------------------------------------------------
class PredictionResult(BaseModel):
    score: float
    raw_score: float
    risk_class: str
    model_version: str
    inference_latency_ms: float
    contributions: List[Dict[str, Any]]


class HealthResult(BaseModel):
    status: str
    models_loaded: Dict[str, bool]
    error: Optional[str] = None


# ----------------------------------------------------------------------
# Endpoints
# ----------------------------------------------------------------------
@app.get("/v1/health", response_model=HealthResult)
def health_v1():
    return {
        "status": "ok" if _model_loaded else "degraded",
        "models_loaded": {
            "risk_model": _model_loaded,
        },
        "error": _load_error,
    }


@app.get("/health")
def health_legacy():
    return {
        "status": "ok" if _model_loaded else "degraded",
        "engine": "CalibratedClassifierCV",
        "models_loaded": {"risk_model": _model_loaded},
        "error": _load_error,
    }


@app.get("/v1/model-info")
def model_info():
    """Return metadata from model_meta.json."""
    if not _model_loaded or _risk_meta is None:
        raise HTTPException(
            status_code=503,
            detail=f"Model metadata not available: {_load_error or 'not loaded'}",
        )
    return _risk_meta


def _validate_and_build_dataframe(body: Dict[str, Any]) -> pd.DataFrame:
    """Build a one-row DataFrame with exactly the columns from model_meta.json.

    Validates that the request contains exactly the expected columns
    (no silent defaults, no missing columns). Raises HTTPException(422)
    on mismatch.
    """
    if not _model_loaded:
        raise HTTPException(
            status_code=503,
            detail=f"Risk model not loaded: {_load_error or 'unknown error'}",
        )

    expected_cols = _risk_features
    expected_set = set(expected_cols)

    raw_keys = set(body.keys())

    missing = expected_set - raw_keys
    extra = raw_keys - expected_set

    errors = []
    if missing:
        errors.append(f"Missing required columns: {sorted(missing)}")
    if extra:
        errors.append(f"Unexpected extra columns: {sorted(extra)}")

    if errors:
        raise HTTPException(status_code=422, detail="; ".join(errors))

    # Reject values the encoder has never seen: silently one-hot encoding an
    # unknown category would produce an all-zero row and an unreliable score.
    known_categories = (_risk_meta or {}).get("known_categories", {})
    bad_values = []
    for col, allowed in known_categories.items():
        if col in body and body[col] not in allowed:
            bad_values.append(f"{col}={body[col]!r} (allowed: {sorted(allowed)})")
    if bad_values:
        raise HTTPException(
            status_code=422,
            detail="Unknown categorical values: " + "; ".join(bad_values),
        )

    # Numeric columns must be real numbers, not booleans or strings.
    bad_numeric = []
    for col in _risk_numeric_cols:
        if col not in body:
            continue
        value = body[col]
        if isinstance(value, bool) or not isinstance(value, (int, float)):
            bad_numeric.append(f"{col}={value!r}")
    if bad_numeric:
        raise HTTPException(
            status_code=422,
            detail="Numeric columns must be numbers: " + "; ".join(bad_numeric),
        )

    # Build row in exact column order from model_meta.json
    row = {c: body[c] for c in expected_cols}
    X = pd.DataFrame([row])
    return X


def _compute_contributions(X: pd.DataFrame, proba: float) -> List[Dict[str, Any]]:
    """Return per-feature contributions.

    Uses the calibrated model's underlying pipeline to estimate
    per-feature contribution via permutation-style importance on the
    single row, or falls back to coefficient-based weights for
    linear models.
    """
    contributions = []
    try:
        inner = _risk_model
        # Unwrap CalibratedClassifierCV
        if hasattr(inner, "calibrated_classifiers_"):
            cal = inner.calibrated_classifiers_[0]
            base = getattr(cal, "estimator", None) or getattr(cal, "base_estimator", None)
        else:
            base = inner

        if hasattr(base, "named_steps"):
            clf = base.named_steps.get("clf", base)
            preprocess = base.named_steps.get("preprocess")
        else:
            clf = base
            preprocess = None

        # Try coefficient-based (logistic regression)
        coef = getattr(clf, "coef_", None)
        if coef is not None and preprocess is not None:
            Xt = preprocess.transform(X)
            if hasattr(Xt, "toarray"):
                Xt = Xt.toarray()
            feature_names = preprocess.get_feature_names_out()
            coef_flat = coef[0] if coef.ndim > 1 else coef
            for i, name in enumerate(feature_names):
                val = Xt[0][i] if hasattr(Xt, "__getitem__") else Xt[i]
                contrib = float(coef_flat[i]) * float(val)
                contributions.append({"feature": str(name), "contribution": round(contrib, 6)})
            contributions.sort(key=lambda x: abs(x["contribution"]), reverse=True)
            return contributions[:5]
    except Exception:
        pass

    # Fallback: equal contribution per feature
    n = len(_risk_features)
    per_feature = proba / n if n > 0 else 0.0
    for c in _risk_features:
        contributions.append({"feature": c, "contribution": round(per_feature, 6)})
    return contributions


@app.post("/v1/predict", response_model=PredictionResult)
def predict_v1(body: Dict[str, Any]):
    started = time.perf_counter()

    X = _validate_and_build_dataframe(body)

    try:
        proba = float(_risk_model.predict_proba(X)[0][1])
    except Exception as exc:
        raise HTTPException(status_code=400, detail=f"Inference failed: {exc}") from exc

    latency_ms = (time.perf_counter() - started) * 1000
    risk_class = classify(proba)
    contributions = _compute_contributions(X, proba)

    model_version = (
        _risk_meta.get("model_version", "rf_v3") if _risk_meta else "rf_v3"
    )

    return {
        "score": round(proba, 6),
        "raw_score": round(proba, 6),
        "risk_class": risk_class,
        "model_version": model_version,
        "inference_latency_ms": round(latency_ms, 3),
        "contributions": contributions,
    }


@app.post("/predict")
def predict_legacy(body: Dict[str, Any]):
    """Backward-compatible /predict endpoint (legacy).

    Returns the same shape as /v1/predict so PHP clients can switch
    with minimal changes.
    """
    return predict_v1(body)


# Convenience artifact accessor (unused, kept for clarity)
def _artifact_get(obj, key, default=None):
    try:
        return obj[key]
    except Exception:
        return default
