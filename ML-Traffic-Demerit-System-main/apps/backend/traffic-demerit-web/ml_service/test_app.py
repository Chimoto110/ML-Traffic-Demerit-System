"""
Tests for FastAPI ML service endpoints.
"""

import json
from fastapi.testclient import TestClient

from app import app

client = TestClient(app)


def test_health():
    """GET /v1/health should return ok with model status."""
    response = client.get("/v1/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "ok"
    assert "models_loaded" in data


def test_health_legacy():
    """GET /health should return ok (backward compat)."""
    response = client.get("/health")
    assert response.status_code == 200


def test_predict_risk_model_loaded():
    """POST /v1/predict returns valid prediction when model is loaded."""
    from app import _risk_model
    if _risk_model is None:
        # Skip if model not loaded in test env
        return

    response = client.post("/v1/predict", json={
        "days_since_last_offense": 30,
        "speed_recorded": 95,
        "zone_type": "highway",
        "weather_conditions": "clear",
        "cumulative_active_demerit_points": 15,
        "past_fine_payment_delays_days": 10,
        "violation_count": 5,
        "new_driver": False,
        "segment_id": 0,
        "distance_to_centroid": 1.5,
        "rule_hit_count": 2,
    })
    assert response.status_code == 200
    data = response.json()
    assert "driver_risk_score" in data
    assert "risk_class" in data
    assert "confidence_level" in data
    assert "inference_latency_ms" in data
    assert "explanation_top_factors" in data
    assert data["risk_class"] in ["low", "moderate", "high"]
    assert 0 <= data["driver_risk_score"] <= 1


def test_predict_model_not_found(monkeypatch):
    """POST /v1/predict returns 503 when model is not loaded."""
    import app as app_module
    original = app_module._risk_model
    app_module._risk_model = None
    try:
        response = client.post("/v1/predict", json={
            "days_since_last_offense": 30,
            "speed_recorded": 95,
            "zone_type": "highway",
            "weather_conditions": "clear",
            "cumulative_active_demerit_points": 15,
            "past_fine_payment_delays_days": 10,
        })
        assert response.status_code == 503
    finally:
        app_module._risk_model = original


def test_predict_bad_input():
    """POST /v1/predict with invalid data returns 422."""
    response = client.post("/v1/predict", json={
        "days_since_last_offense": -1,  # invalid: must be >= 0
        "speed_recorded": 95,
        "zone_type": "highway",
        "weather_conditions": "clear",
        "cumulative_active_demerit_points": 15,
        "past_fine_payment_delays_days": 10,
    })
    assert response.status_code == 422


def test_segments():
    """GET /v1/segments returns segment profiles."""
    response = client.get("/v1/segments")
    assert response.status_code == 200
    data = response.json()
    assert "model_version" in data
    assert "k" in data
    assert "centroids" in data


def test_rules():
    """GET /v1/rules returns association rules."""
    response = client.get("/v1/rules?limit=5")
    assert response.status_code == 200
    data = response.json()
    assert "total" in data
    assert "rules" in data
    assert len(data["rules"]) <= 5


def test_rules_filter_min_lift():
    """GET /v1/rules with min_lift filter."""
    response = client.get("/v1/rules?min_lift=2.0")
    assert response.status_code == 200
    data = response.json()
    for rule in data["rules"]:
        assert rule["lift"] >= 2.0


def test_model_info():
    """GET /v1/model-info returns model metadata."""
    response = client.get("/v1/model-info")
    assert response.status_code == 200
    data = response.json()
    assert "models" in data
    assert "risk" in data["models"]
    assert "segmentation" in data["models"]
    assert "rules" in data["models"]


def test_segment_endpoint():
    """POST /v1/segment returns cluster assignment."""
    from app import _kmeans_model
    if _kmeans_model is None:
        return

    response = client.post("/v1/segment", json={
        "violation_count": 5,
        "cumulative_active_demerit_points": 15,
        "avg_speed_over_limit": 10.0,
        "past_fine_payment_delays_days": 5.0,
        "unpaid_fine_ratio": 0.2,
        "days_since_last_offense": 30,
        "share_at_night": 0.3,
    })
    assert response.status_code == 200
    data = response.json()
    assert "cluster_id" in data
    assert "distance_to_centroid" in data
