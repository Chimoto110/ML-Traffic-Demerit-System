"""Tests for the v3 FastAPI risk-scoring service.

Run from this directory with:

    python -m pytest test_app.py -v

The prediction tests are skipped automatically when the model artifacts
are missing, so the suite still works on a fresh checkout before training.
"""

import pytest
from fastapi.testclient import TestClient

import app as ml_app

client = TestClient(ml_app.app)

# A complete, valid request: exactly the six features in model_meta.json.
BASE_PAYLOAD = {
    "days_since_last_offense": 120,
    "speed_recorded": 95,
    "zone_type": "urban",
    "weather_conditions": "clear",
    "cumulative_active_demerit_points": 4,
    "past_fine_payment_delays_days": 10,
}

needs_model = pytest.mark.skipif(
    not ml_app._model_loaded,
    reason=f"risk model not loaded: {ml_app._load_error}",
)


@pytest.fixture()
def payload():
    return dict(BASE_PAYLOAD)


@pytest.fixture()
def prediction(payload):
    response = client.post("/v1/predict", json=payload)
    assert response.status_code == 200
    return response.json()


# ----------------------------------------------------------------------
# Health and metadata
# ----------------------------------------------------------------------
def test_v1_health_reports_model_status():
    response = client.get("/v1/health")
    assert response.status_code == 200
    body = response.json()
    assert body["status"] in {"ok", "degraded"}
    assert "risk_model" in body["models_loaded"]
    if ml_app._model_loaded:
        assert body["status"] == "ok"
        assert body["models_loaded"]["risk_model"] is True
        assert body["error"] is None


def test_legacy_health_endpoint():
    response = client.get("/health")
    assert response.status_code == 200
    body = response.json()
    assert body["engine"] == "CalibratedClassifierCV"
    assert "models_loaded" in body


@needs_model
def test_model_info_exposes_metadata():
    response = client.get("/v1/model-info")
    assert response.status_code == 200
    meta = response.json()
    assert meta["model_version"] == "rf_v3"
    assert meta["feature_cols"] == list(BASE_PAYLOAD.keys())
    assert len(meta["feature_cols"]) == 6
    assert set(meta["known_categories"]) == {"zone_type", "weather_conditions"}


# ----------------------------------------------------------------------
# Prediction contract
# ----------------------------------------------------------------------
@needs_model
def test_predict_returns_expected_shape(prediction):
    assert set(prediction) == {
        "score",
        "raw_score",
        "risk_class",
        "model_version",
        "inference_latency_ms",
        "contributions",
    }
    assert prediction["model_version"] == "rf_v3"


@needs_model
def test_predict_score_and_class_are_consistent(prediction):
    score = prediction["score"]
    assert 0.0 <= score <= 1.0
    if score >= ml_app.HIGH_CUTOFF:
        assert prediction["risk_class"] == "high"
    elif score >= ml_app.LOW_CUTOFF:
        assert prediction["risk_class"] == "moderate"
    else:
        assert prediction["risk_class"] == "low"
    assert prediction["inference_latency_ms"] >= 0


@needs_model
def test_predict_is_deterministic(payload):
    first = client.post("/v1/predict", json=payload).json()
    second = client.post("/v1/predict", json=payload).json()
    assert first["score"] == second["score"]
    assert first["risk_class"] == second["risk_class"]


@needs_model
def test_predict_contributions_are_ranked(prediction):
    contributions = prediction["contributions"]
    assert 0 < len(contributions) <= 5
    magnitudes = [abs(c["contribution"]) for c in contributions]
    assert magnitudes == sorted(magnitudes, reverse=True)
    for item in contributions:
        assert set(item) == {"feature", "contribution"}


@needs_model
def test_legacy_predict_matches_v1(payload):
    v1 = client.post("/v1/predict", json=payload)
    legacy = client.post("/predict", json=payload)
    assert v1.status_code == legacy.status_code == 200
    assert legacy.json()["score"] == v1.json()["score"]


# ----------------------------------------------------------------------
# Input validation
# ----------------------------------------------------------------------
@needs_model
def test_missing_feature_returns_422(payload):
    payload.pop("speed_recorded")
    response = client.post("/v1/predict", json=payload)
    assert response.status_code == 422
    assert "speed_recorded" in response.json()["detail"]


@needs_model
def test_extra_feature_returns_422(payload):
    payload["gender"] = "male"
    response = client.post("/v1/predict", json=payload)
    assert response.status_code == 422
    assert "gender" in response.json()["detail"]


@needs_model
@pytest.mark.parametrize(
    "field,bad_value",
    [
        ("zone_type", "moon"),
        ("weather_conditions", "snow"),
    ],
)
def test_unknown_category_returns_422(payload, field, bad_value):
    payload[field] = bad_value
    response = client.post("/v1/predict", json=payload)
    assert response.status_code == 422
    assert field in response.json()["detail"]


@needs_model
def test_non_numeric_value_returns_422(payload):
    payload["speed_recorded"] = "very fast"
    response = client.post("/v1/predict", json=payload)
    assert response.status_code == 422
    assert "speed_recorded" in response.json()["detail"]


@needs_model
def test_empty_body_returns_422():
    response = client.post("/v1/predict", json={})
    assert response.status_code == 422


# ----------------------------------------------------------------------
# Risk banding helper
# ----------------------------------------------------------------------
@pytest.mark.parametrize(
    "score,expected",
    [
        (0.0, "low"),
        (0.3999, "low"),
        (ml_app.LOW_CUTOFF, "moderate"),
        (0.55, "moderate"),
        (0.6999, "moderate"),
        (ml_app.HIGH_CUTOFF, "high"),
        (0.95, "high"),
    ],
)
def test_classify_boundaries(score, expected):
    assert ml_app.classify(score) == expected
