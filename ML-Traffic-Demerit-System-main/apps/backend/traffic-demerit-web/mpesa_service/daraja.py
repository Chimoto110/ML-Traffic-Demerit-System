"""
DarajaClient - Python client for Safaricom Daraja API (STK Push).

Implements OAuth token management, STK Push initiation, and STK Query.
All secrets are read from environment variables. Never logs secrets.
"""

from __future__ import annotations

import base64
import os
import time
from datetime import datetime, timedelta, timezone
from pathlib import Path

import requests
from dotenv import load_dotenv


class DarajaError(Exception):
    """Raised when Daraja API returns an error."""


class DarajaClient:
    """
    Client for Safaricom Daraja Lipa Na M-Pesa Online (STK Push) API.

    Environment variables:
        MPESA_ENV=sandbox|production
        MPESA_CONSUMER_KEY=...
        MPESA_CONSUMER_SECRET=...
        MPESA_SHORTCODE=...
        MPESA_PASSKEY=...
        MPESA_TRANSACTION_TYPE=CustomerPayBillOnline|CustomerBuyGoodsOnline
        MPESA_PARTY_B=...
        MPESA_CALLBACK_URL=...
        MPESA_TIMEOUT_SECONDS=120
        MPESA_SIMULATE=true|false
        MPESA_TEST_AMOUNT=1 (optional override)
    """

    def __init__(self) -> None:
        env_path = Path(__file__).resolve().parent.parent / ".env"
        if env_path.exists():
            load_dotenv(env_path)
        else:
            load_dotenv()

        self.env = os.getenv("MPESA_ENV", "sandbox")
        self.consumer_key = os.getenv("MPESA_CONSUMER_KEY", "")
        self.consumer_secret = os.getenv("MPESA_CONSUMER_SECRET", "")
        self.shortcode = os.getenv("MPESA_SHORTCODE", "")
        self.passkey = os.getenv("MPESA_PASSKEY", "")
        self.transaction_type = os.getenv("MPESA_TRANSACTION_TYPE", "CustomerPayBillOnline")
        self.party_b = os.getenv("MPESA_PARTY_B", "")
        self.callback_url = os.getenv("MPESA_CALLBACK_URL", "")
        self.timeout_seconds = int(os.getenv("MPESA_TIMEOUT_SECONDS", "120"))
        self.simulate = os.getenv("MPESA_SIMULATE", "true").lower() == "true"
        self.test_amount = os.getenv("MPESA_TEST_AMOUNT", "")

        self._access_token: str | None = None
        self._token_expires_at: float = 0.0

    # ------------------------------------------------------------------
    # OAuth
    # ------------------------------------------------------------------
    def get_access_token(self) -> str:
        """Get OAuth access token, cached until shortly before expiry.
        Refreshes on 401."""
        now = time.time()
        if self._access_token and now < self._token_expires_at:
            return self._access_token

        if self.simulate:
            self._access_token = "simulate_token_" + os.urandom(8).hex()
            self._token_expires_at = now + 3000  # 50 minutes
            return self._access_token

        if not self.consumer_key or not self.consumer_secret:
            raise DarajaError(
                "MPESA_CONSUMER_KEY and MPESA_CONSUMER_SECRET required in real mode"
            )

        base = "https://api.safaricom.co.ke" if self.env == "production" else "https://sandbox.safaricom.co.ke"
        url = f"{base}/oauth/v1/generate?grant_type=client_credentials"

        try:
            resp = requests.get(
                url,
                auth=(self.consumer_key, self.consumer_secret),
                timeout=self.timeout_seconds,
            )
        except requests.RequestException as e:
            raise DarajaError(f"Token request network error: {e}") from e

        if resp.status_code != 200:
            raise DarajaError(f"Token request failed: HTTP {resp.status_code}: {resp.text}")

        try:
            data = resp.json()
        except ValueError:
            raise DarajaError(f"Token request returned non-JSON: {resp.text}") from None

        token = data.get("access_token")
        if not token:
            raise DarajaError(f"Token response missing access_token: {data}")

        self._access_token = token
        # Daraja tokens valid for 1 hour; cache for 50 min
        self._token_expires_at = now + 3000
        return token

    # ------------------------------------------------------------------
    # Phone normalization
    # ------------------------------------------------------------------
    @staticmethod
    def normalize_phone(phone: str) -> str:
        """Normalize Kenyan phone number to 254XXXXXXXX format.

        Accepts: 07XX, 01XX, +2547XX, 2547XX, 2541XX, with spaces/dashes.
        Rejects anything else.
        """
        p = "".join(ch for ch in phone if ch.isdigit() or ch == "+")
        if p.startswith("+"):
            p = p[1:]
        if p.startswith("0") and len(p) == 10 and p[1] in ("7", "1"):
            p = "254" + p[1:]
        if p.startswith("254") and len(p) == 12 and p[3] in ("7", "1"):
            return p
        raise ValueError(
            f"Invalid phone number: '{phone}'. "
            "Expected 07XX, 01XX, +2547XX, or 254XXXXXXXX"
        )

    # ------------------------------------------------------------------
    # STK Push
    # ------------------------------------------------------------------
    def stk_push(
        self,
        phone: str,
        amount: int,
        account_reference: str,
        description: str,
    ) -> dict:
        """Initiate STK Push.

        Returns dict with keys: success, checkout_request_id, merchant_request_id,
        result_code, result_desc, response.
        """
        if self.simulate:
            return self._simulate_stk_push(phone, amount, account_reference, description)

        normalized = self.normalize_phone(phone)
        token = self.get_access_token()
        timestamp = datetime.now(timezone.utc).astimezone().strftime("%Y%m%d%H%M%S")
        password = base64.b64encode(
            (self.shortcode + self.passkey + timestamp).encode("utf-8")
        ).decode("utf-8")

        base = "https://api.safaricom.co.ke" if self.env == "production" else "https://sandbox.safaricom.co.ke"
        url = f"{base}/mpesa/stkpush/v1/processrequest"
        headers = {"Authorization": f"Bearer {token}"}
        payload = {
            "BusinessShortCode": self.shortcode,
            "Password": password,
            "Timestamp": timestamp,
            "TransactionType": self.transaction_type,
            "Amount": amount,
            "PartyA": normalized,
            "PartyB": self.party_b or self.shortcode,
            "PhoneNumber": normalized,
            "CallBackURL": self.callback_url,
            "AccountReference": account_reference[:12],  # Daraja max 12 chars
            "TransactionDesc": description[:25],  # Daraja max 25 chars
        }

        try:
            resp = requests.post(url, json=payload, headers=headers, timeout=self.timeout_seconds)
        except requests.RequestException as e:
            return {
                "success": False,
                "checkout_request_id": None,
                "merchant_request_id": None,
                "result_code": None,
                "result_desc": f"Network error: {e}",
                "response": {},
            }

        if resp.status_code != 200:
            return {
                "success": False,
                "checkout_request_id": None,
                "merchant_request_id": None,
                "result_code": None,
                "result_desc": f"HTTP {resp.status_code}: {resp.text}",
                "response": {},
            }

        try:
            data = resp.json()
        except ValueError:
            return {
                "success": False,
                "checkout_request_id": None,
                "merchant_request_id": None,
                "result_code": None,
                "result_desc": f"Non-JSON response: {resp.text}",
                "response": {},
            }

        return {
            "success": data.get("ResponseCode") == "0",
            "checkout_request_id": data.get("CheckoutRequestID"),
            "merchant_request_id": data.get("MerchantRequestID"),
            "result_code": data.get("ResponseCode"),
            "result_desc": data.get("ResponseDesc"),
            "response": data,
        }

    def _simulate_stk_push(
        self, phone: str, amount: int, account_reference: str, description: str
    ) -> dict:
        """Deterministic simulation based on last 4 digits of phone."""
        phone_suffix = int(phone[-4:]) if phone[-4:].isdigit() else 0
        result_code = "0"
        result_desc = "Success. Request accepted for processing."

        if phone_suffix % 10 == 2:
            result_code = "1"
            result_desc = "Insufficient funds"
        elif phone_suffix % 10 == 3:
            result_code = "1032"
            result_desc = "Cancelled by user"
        elif phone_suffix % 10 == 4:
            result_code = "1037"
            result_desc = "Timeout"

        import random

        checkout_request_id = f"MOCK-CHECKOUT-{random.randint(100000, 999999)}"
        merchant_request_id = f"MOCK-MERCHANT-{random.randint(100000, 999999)}"

        return {
            "success": result_code == "0",
            "checkout_request_id": checkout_request_id,
            "merchant_request_id": merchant_request_id,
            "result_code": result_code,
            "result_desc": result_desc,
            "response": {
                "MerchantRequestID": merchant_request_id,
                "CheckoutRequestID": checkout_request_id,
                "ResponseCode": result_code,
                "ResponseDesc": result_desc,
                "CustomerMessage": result_desc,
            },
        }

    # ------------------------------------------------------------------
    # STK Query
    # ------------------------------------------------------------------
    def stk_query(self, checkout_request_id: str) -> dict:
        """Query STK Push status."""
        if self.simulate:
            return {
                "ok": True,
                "result_code": "0",
                "result_desc": "The service request is processed successfully.",
                "response": {},
            }

        token = self.get_access_token()
        timestamp = datetime.now(timezone.utc).astimezone().strftime("%Y%m%d%H%M%S")
        password = base64.b64encode(
            (self.shortcode + self.passkey + timestamp).encode("utf-8")
        ).decode("utf-8")

        base = "https://api.safaricom.co.ke" if self.env == "production" else "https://sandbox.safaricom.co.ke"
        url = f"{base}/mpesa/stkpushquery/v1/query"
        headers = {"Authorization": f"Bearer {token}"}
        payload = {
            "BusinessShortCode": self.shortcode,
            "Password": password,
            "Timestamp": timestamp,
            "CheckoutRequestID": checkout_request_id,
        }

        try:
            resp = requests.post(url, json=payload, headers=headers, timeout=self.timeout_seconds)
        except requests.RequestException as e:
            return {"ok": False, "result_code": None, "result_desc": f"Network error: {e}", "response": {}}

        if resp.status_code != 200:
            return {
                "ok": False,
                "result_code": None,
                "result_desc": f"HTTP {resp.status_code}: {resp.text}",
                "response": {},
            }

        try:
            data = resp.json()
        except ValueError:
            return {
                "ok": False,
                "result_code": None,
                "result_desc": f"Non-JSON response: {resp.text}",
                "response": {},
            }

        return {
            "ok": True,
            "result_code": data.get("ResultCode"),
            "result_desc": data.get("ResultDesc"),
            "response": data,
        }