"""
MPesa STK Push service (FastAPI, port 8003).

Endpoints:
    POST /stk/push      {payment_id, phone, amount, reference, description}
    POST /stk/query     {checkout_request_id}
    GET  /health

All endpoints require header: X-Internal-Key = INTERNAL_API_KEY
"""

from __future__ import annotations

import os
from pathlib import Path

from dotenv import load_dotenv
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel, Field

from daraja import DarajaClient, DarajaError

# Load .env from service directory
env_path = Path(__file__).resolve().parent / ".env"
if env_path.exists():
    load_dotenv(env_path)
else:
    load_dotenv()

INTERNAL_API_KEY = os.getenv("INTERNAL_API_KEY", "")

app = FastAPI(title="MPesa STK Push Service", version="1.0.0")

# Singleton Daraja client
_client: DarajaClient | None = None


def get_client() -> DarajaClient:
    global _client
    if _client is None:
        _client = DarajaClient()
    return _client


def verify_key(x_internal_key: str | None) -> None:
    if not INTERNAL_API_KEY or x_internal_key != INTERNAL_API_KEY:
        raise HTTPException(status_code=401, detail="Invalid or missing X-Internal-Key")


# ------------------------------------------------------------------
# Request/Response models
# ------------------------------------------------------------------
class StkPushRequest(BaseModel):
    payment_id: str | int | None = None
    phone: str = Field(..., description="Phone number (07XX, 01XX, +2547XX, 254XXXXXXXX)")
    amount: int = Field(..., gt=0, description="Amount in KES (whole shillings)")
    reference: str = Field(..., description="Account reference (e.g. violation ID)")
    description: str = Field(..., description="Transaction description")


class StkPushResponse(BaseModel):
    ok: bool
    merchant_request_id: str | None = None
    checkout_request_id: str | None = None
    response_code: str | None = None
    customer_message: str | None = None


class StkQueryRequest(BaseModel):
    checkout_request_id: str


class StkQueryResponse(BaseModel):
    ok: bool
    result_code: str | None = None
    result_desc: str | None = None
    status: str | None = None


# ------------------------------------------------------------------
# Endpoints
# ------------------------------------------------------------------
@app.get("/health")
def health() -> dict:
    return {"status": "ok", "service": "mpesa-stk", "port": 8003}


@app.post("/stk/push", response_model=StkPushResponse)
def stk_push(req: StkPushRequest, x_internal_key: str | None = Header(None)) -> StkPushResponse:
    verify_key(x_internal_key)

    client = get_client()
    try:
        result = client.stk_push(
            phone=req.phone,
            amount=req.amount,
            account_reference=req.reference,
            description=req.description,
        )
    except DarajaError as e:
        raise HTTPException(status_code=502, detail=str(e)) from e

    return StkPushResponse(
        ok=result["success"],
        merchant_request_id=result.get("merchant_request_id"),
        checkout_request_id=result.get("checkout_request_id"),
        response_code=result.get("result_code"),
        customer_message=result.get("result_desc"),
    )


@app.post("/stk/query", response_model=StkQueryResponse)
def stk_query(req: StkQueryRequest, x_internal_key: str | None = Header(None)) -> StkQueryResponse:
    verify_key(x_internal_key)

    client = get_client()
    try:
        result = client.stk_query(req.checkout_request_id)
    except DarajaError as e:
        raise HTTPException(status_code=502, detail=str(e)) from e

    # Map result_code to a friendly status
    rc = result.get("result_code")
    if rc == "0":
        status = "completed"
    elif rc in ("1", "1032", "1037", "2001"):
        status = "failed"
    elif rc is None:
        status = "pending"
    else:
        status = "failed"

    return StkQueryResponse(
        ok=result.get("ok", False),
        result_code=result.get("result_code"),
        result_desc=result.get("result_desc"),
        status=status,
    )