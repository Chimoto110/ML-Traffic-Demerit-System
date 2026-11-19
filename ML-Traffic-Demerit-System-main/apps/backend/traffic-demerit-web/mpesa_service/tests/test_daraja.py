"""
Tests for daraja.py - the Python Daraja client.
"""
import os
from unittest.mock import patch

import pytest

from daraja import DarajaClient, DarajaError


def test_normalize_phone_valid():
    c = DarajaClient()
    assert c.normalize_phone("0712345678") == "254712345678"
    assert c.normalize_phone("+254712345678") == "254712345678"
    assert c.normalize_phone("254712345678") == "254712345678"
    assert c.normalize_phone("  0712-345-678  ") == "254712345678"
    assert c.normalize_phone("0712345678") == "254712345678"
    assert c.normalize_phone("0112345678") == "254112345678"


def test_normalize_phone_invalid():
    c = DarajaClient()
    with pytest.raises(ValueError):
        c.normalize_phone("12345")
    with pytest.raises(ValueError):
        c.normalize_phone("")
    with pytest.raises(ValueError):
        c.normalize_phone("071234567")  # too short


def test_get_access_token_simulate():
    c = DarajaClient()
    c.simulate = True
    token = c.get_access_token()
    assert token.startswith("simulate_token_")


def test_stk_push_simulate_success():
    c = DarajaClient()
    c.simulate = True
    c.shortcode = "174379"
    c.passkey = "test_passkey"
    result = c.stk_push("254700000001", 100, "V1", "Test payment")
    assert result["success"] is True
    assert result["checkout_request_id"] is not None
    assert result["result_code"] == "0"


def test_stk_push_simulate_insufficient_funds():
    c = DarajaClient()
    c.simulate = True
    c.shortcode = "174379"
    c.passkey = "test_passkey"
    result = c.stk_push("254700000002", 100, "V1", "Test payment")
    assert result["success"] is False
    assert result["result_code"] == "1"


def test_stk_push_simulate_cancelled():
    c = DarajaClient()
    c.simulate = True
    c.shortcode = "174379"
    c.passkey = "test_passkey"
    result = c.stk_push("254700000003", 100, "V1", "Test payment")
    assert result["success"] is False
    assert result["result_code"] == "1032"


def test_stk_query_simulate():
    c = DarajaClient()
    c.simulate = True
    c.shortcode = "174379"
    c.passkey = "test_passkey"
    result = c.stk_query("MOCK-CHECKOUT-123")
    assert result["ok"] is True
    assert result["result_code"] == "0"


def test_missing_credentials_real_mode():
    c = DarajaClient()
    c.simulate = False
    c.consumer_key = ""
    c.consumer_secret = ""
    with pytest.raises(DarajaError):
        c.get_access_token()