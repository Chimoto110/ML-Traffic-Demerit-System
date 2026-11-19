"""
Standalone M-Pesa STK Push test script.

Reads .env and sends ONE STK push to a phone number passed on the command line,
then polls the STK query endpoint for up to 90 seconds.

Usage:
    python stk_test.py 0712345678

Environment variables required (from .env):
    MPESA_ENV=sandbox|production
    MPESA_CONSUMER_KEY=...
    MPESA_CONSUMER_SECRET=...
    MPESA_SHORTCODE=...
    MPESA_PASSKEY=...
    MPESA_CALLBACK_URL=https://... (any reachable HTTPS URL)
    MPESA_TRANSACTION_TYPE=CustomerPayBillOnline|CustomerBuyGoodsOnline
    MPESA_PARTY_B=...
    MPESA_TIMEOUT_SECONDS=120
    MPESA_TEST_AMOUNT=1 (optional: override amount for testing)
"""
from __future__ import annotations

import os
import sys
import time
import base64
from datetime import datetime, timezone
from pathlib import Path

try:
    from dotenv import load_dotenv
except ImportError:
    print("ERROR: python-dotenv not installed. Run: pip install python-dotenv requests")
    sys.exit(1)

import requests


def load_env() -> dict:
    """Load .env file and return config dict."""
    env_path = Path(__file__).resolve().parent.parent / ".env"
    if env_path.exists():
        load_dotenv(env_path)
    else:
        # Try current working directory
        load_dotenv()

    return {
        "env": os.getenv("MPESA_ENV", "sandbox"),
        "consumer_key": os.getenv("MPESA_CONSUMER_KEY", ""),
        "consumer_secret": os.getenv("MPESA_CONSUMER_SECRET", ""),
        "shortcode": os.getenv("MPESA_SHORTCODE", ""),
        "passkey": os.getenv("MPESA_PASSKEY", ""),
        "transaction_type": os.getenv("MPESA_TRANSACTION_TYPE", "CustomerPayBillOnline"),
        "party_b": os.getenv("MPESA_PARTY_B", ""),
        "callback_url": os.getenv("MPESA_CALLBACK_URL", ""),
        "test_amount": os.getenv("MPESA_TEST_AMOUNT", ""),
        "timeout_seconds": int(os.getenv("MPESA_TIMEOUT_SECONDS", "120")),
    }


def validate_config(cfg: dict) -> None:
    """Validate required config values are present."""
    missing = []
    if not cfg["consumer_key"]:
        missing.append("MPESA_CONSUMER_KEY")
    if not cfg["consumer_secret"]:
        missing.append("MPESA_CONSUMER_SECRET")
    if not cfg["shortcode"]:
        missing.append("MPESA_SHORTCODE")
    if not cfg["passkey"]:
        missing.append("MPESA_PASSKEY")
    if not cfg["callback_url"]:
        missing.append("MPESA_CALLBACK_URL")

    if missing:
        print(f"\nERROR: Missing required environment variables: {', '.join(missing)}")
        print("Please set them in your .env file before running this script.")
        sys.exit(1)


def normalize_phone(phone: str) -> str:
    """Normalize Kenyan phone number to 254XXXXXXXX format."""
    p = "".join(ch for ch in phone if ch.isdigit() or ch == "+")
    if p.startswith("+"):
        p = p[1:]
    if p.startswith("0") and len(p) == 10 and p[1] in ("7", "1"):
        p = "254" + p[1:]
    if p.startswith("254") and len(p) == 12 and p[3] in ("7", "1"):
        return p
    raise ValueError(
        f"Invalid phone number format: '{phone}'. "
        "Expected 07XX, 01XX, +2547XX, or 254XXXXXXXX"
    )


def base_url(env: str) -> str:
    return "https://api.safaricom.co.ke" if env == "production" else "https://sandbox.safaricom.co.ke"


def get_access_token(cfg: dict) -> str:
    """OAuth client_credentials grant."""
    url = f"{base_url(cfg['env'])}/oauth/v1/generate?grant_type=client_credentials"
    try:
        resp = requests.get(
            url,
            auth=(cfg["consumer_key"], cfg["consumer_secret"]),
            timeout=cfg["timeout_seconds"],
        )
    except requests.RequestException as e:
        print(f"\nERROR: Token request failed - network error: {e}")
        sys.exit(1)

    if resp.status_code != 200:
        print(f"\nERROR: Token request failed - HTTP {resp.status_code}: {resp.text}")
        sys.exit(1)

    try:
        data = resp.json()
    except ValueError:
        print(f"\nERROR: Token request returned non-JSON: {resp.text}")
        sys.exit(1)

    token = data.get("access_token")
    if not token:
        print(f"\nERROR: Token response missing access_token: {data}")
        sys.exit(1)

    return token


def generate_password(shortcode: str, passkey: str, timestamp: str) -> str:
    """base64(shortcode + passkey + timestamp)."""
    return base64.b64encode(
        (shortcode + passkey + timestamp).encode("utf-8")
    ).decode("utf-8")


def stk_push(cfg: dict, phone: str, amount: int, account_ref: str, description: str) -> dict:
    """Initiate STK Push. Returns the parsed JSON response."""
    timestamp = datetime.now(timezone.utc).astimezone().strftime("%Y%m%d%H%M%S")
    # Use Africa/Nairobi (UTC+3) - datetime above uses local tz; for safety
    # we compute Nairobi time explicitly:
    from datetime import timedelta

    nairobi_now = datetime.utcnow() + timedelta(hours=3)
    timestamp = nairobi_now.strftime("%Y%m%d%H%M%S")
    password = generate_password(cfg["shortcode"], cfg["passkey"], timestamp)

    url = f"{base_url(cfg['env'])}/mpesa/stkpush/v1/processrequest"
    headers = {"Authorization": f"Bearer {get_access_token(cfg)}"}
    payload = {
        "BusinessShortCode": cfg["shortcode"],
        "Password": password,
        "Timestamp": timestamp,
        "TransactionType": cfg["transaction_type"],
        "Amount": amount,
        "PartyA": phone,
        "PartyB": cfg["party_b"] or cfg["shortcode"],
        "PhoneNumber": phone,
        "CallBackURL": cfg["callback_url"],
        "AccountReference": account_ref[:12],  # Daraja max 12 chars
        "TransactionDesc": description[:25],  # Daraja max 25 chars
    }

    try:
        resp = requests.post(url, json=payload, headers=headers, timeout=cfg["timeout_seconds"])
    except requests.RequestException as e:
        print(f"\nERROR: STK push request failed - network error: {e}")
        sys.exit(1)

    if resp.status_code != 200:
        print(f"\nERROR: STK push rejected - HTTP {resp.status_code}: {resp.text}")
        sys.exit(1)

    try:
        data = resp.json()
    except ValueError:
        print(f"\nERROR: STK push returned non-JSON: {resp.text}")
        sys.exit(1)

    return data


def stk_query(cfg: dict, checkout_request_id: str) -> dict:
    """Query STK Push status."""
    timestamp = datetime.utcnow() + timedelta(hours=3)
    timestamp_str = timestamp.strftime("%Y%m%d%H%M%S")
    password = generate_password(cfg["shortcode"], cfg["passkey"], timestamp_str)

    url = f"{base_url(cfg['env'])}/mpesa/stkpushquery/v1/query"
    headers = {"Authorization": f"Bearer {get_access_token(cfg)}"}
    payload = {
        "BusinessShortCode": cfg["shortcode"],
        "Password": password,
        "Timestamp": timestamp_str,
        "CheckoutRequestID": checkout_request_id,
    }

    try:
        resp = requests.post(url, json=payload, headers=headers, timeout=cfg["timeout_seconds"])
    except requests.RequestException as e:
        return {"ok": False, "result_code": None, "result_desc": f"Network error: {e}"}

    if resp.status_code != 200:
        return {"ok": False, "result_code": None, "result_desc": f"HTTP {resp.status_code}: {resp.text}"}

    try:
        data = resp.json()
    except ValueError:
        return {"ok": False, "result_code": None, "result_desc": f"Non-JSON: {resp.text}"}

    return {
        "ok": True,
        "result_code": data.get("ResultCode"),
        "result_desc": data.get("ResultDesc"),
        "response": data,
    }


def print_result(result_code: str | None, result_desc: str | None) -> None:
    """Print result in plain English."""
    if result_code == "0":
        print("\n=== PAYMENT SUCCESSFUL ===")
        print(f"Result: {result_desc}")
        print("The customer has completed the payment on their phone.")
    elif result_code == "1":
        print("\n=== PAYMENT FAILED: INSUFFICIENT FUNDS ===")
        print(f"Reason: {result_desc}")
    elif result_code == "1032":
        print("\n=== PAYMENT CANCELLED BY USER ===")
        print(f"Reason: {result_desc}")
    elif result_code == "1037":
        print("\n=== PAYMENT TIMEOUT ===")
        print(f"Reason: {result_desc}")
    elif result_code == "2001":
        print("\n=== PAYMENT FAILED: WRONG PIN ===")
        print(f"Reason: {result_desc}")
    elif result_code is None:
        print(f"\n=== POLLING ERROR ===")
        print(f"Reason: {result_desc}")
    else:
        print(f"\n=== PAYMENT FAILED (code {result_code}) ===")
        print(f"Reason: {result_desc}")


def main() -> None:
    if len(sys.argv) < 2:
        print("Usage: python stk_test.py <phone_number>")
        print("Example: python stk_test.py 0712345678")
        sys.exit(1)

    phone_raw = sys.argv[1]

    try:
        phone = normalize_phone(phone_raw)
    except ValueError as e:
        print(f"\nERROR: {e}")
        sys.exit(1)

    cfg = load_env()
    validate_config(cfg)

    # Determine amount
    amount_str = cfg["test_amount"] or "1"
    try:
        amount = int(amount_str)
    except ValueError:
        print(f"\nERROR: MPESA_TEST_AMOUNT must be an integer, got '{amount_str}'")
        sys.exit(1)

    if amount <= 0:
        print("\nERROR: Amount must be greater than zero.")
        sys.exit(1)

    print(f"\n=== M-Pesa STK Push Test ===")
    print(f"Environment: {cfg['env']}")
    print(f"Phone: {phone}")
    print(f"Amount: KES {amount}")
    print(f"Shortcode: {cfg['shortcode']}")
    print(f"Callback URL: {cfg['callback_url']}")
    print()

    # Step 1: Initiate STK Push
    print("[1/2] Sending STK Push...")
    try:
        push_result = stk_push(cfg, phone, amount, "VTEST", "Traffic fine payment - test")
    except SystemExit:
        raise
    except Exception as e:
        print(f"\nERROR: STK push failed - {e}")
        sys.exit(1)

    response_code = push_result.get("ResponseCode")
    response_desc = push_result.get("ResponseDesc")
    customer_message = push_result.get("CustomerMessage")
    checkout_request_id = push_result.get("CheckoutRequestID")
    merchant_request_id = push_result.get("MerchantRequestID")

    print(f"  Response Code: {response_code}")
    print(f"  Response Desc: {response_desc}")
    print(f"  Customer Message: {customer_message}")
    print(f"  Checkout Request ID: {checkout_request_id}")
    print(f"  Merchant Request ID: {merchant_request_id}")

    if response_code != "0":
        print(f"\n=== STK PUSH REJECTED ===")
        print(f"Daraja rejected the request: {response_desc}")
        sys.exit(1)

    print("\nSTK Push accepted. Prompt sent to your phone.")
    print("Please enter your M-Pesa PIN on your phone...")
    print()

    # Step 2: Poll for up to 90 seconds
    max_wait = 90
    poll_interval = 5
    elapsed = 0

    print("[2/2] Polling payment status (up to 90 seconds)...")
    while elapsed < max_wait:
        time.sleep(poll_interval)
        elapsed += poll_interval

        query_result = stk_query(cfg, checkout_request_id)
        result_code = query_result.get("result_code")
        result_desc = query_result.get("result_desc")

        print(f"  [{elapsed:3d}s] Query: code={result_code}, desc={result_desc}")

        if result_code == "0":
            print_result(result_code, result_desc)
            # Try to get receipt from response
            resp_data = query_result.get("response", {})
            receipt = (
                resp_data.get("ReceiptNumber")
                or resp_data.get("MpesaReceiptNumber")
                or resp_data.get("TransactionReceipt")
            )
            if receipt:
                print(f"Receipt: {receipt}")
            sys.exit(0)

        if result_code in ("1", "1032", "1037", "2001"):
            print_result(result_code, result_desc)
            sys.exit(0)

    print(f"\n=== POLLING TIMEOUT ({max_max_wait := max_wait}s) ===")
    print("No final result received from Daraja within the timeout period.")
    print("The STK push may still be pending on the customer's phone.")
    sys.exit(1)


if __name__ == "__main__":
    main()