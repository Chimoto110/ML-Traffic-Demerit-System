# Payment Debug — Root Cause Analysis & Fix

**Date:** 2026-11-04  
**Bug:** Driver cannot pay a fine. Toast shows "Payment failed - Failed to initiate payment. Please try again."  
**Affected violations:** #824, #1160, #1365 (driver: Mwangi Njeri, mwangi.njeri@example.com)  
**Status:** FIXED

---

## Root Cause

### Primary cause: Missing phone number in payment request

**File: `app/Services/MpesaGateway.php`, lines 41-44**

```php
$phone = $payload['phone'] ?? null;
if (! $phone) {
    return new GatewayResult(false, null, null, 'Phone number is required.', [], null);
}
```

**File: `apps/frontend/traffic-demerit-portal/src/api/appClient.js`, lines 345-351**

```javascript
initiate: async (violationId, method = 'mpesa') => {
    return request(`/violations/${violationId}/pay`, {
        method: 'POST',
        body: { method },   // ❌ No phone number sent
        base: 'root',
    });
},
```

The frontend called `POST /api/violations/{id}/pay` with only `{ method: "mpesa" }` — no phone number. The M-Pesa gateway requires a phone number for STK Push, so it returned `failure_reason = "Phone number is required."` The payment row was created as `pending`, then immediately updated to `failed`.

**File: `app\Http\Controllers\PaymentController.php`, lines 117-120**

```php
return response()->json([
    'message' => 'Failed to initiate payment. Please try again.',
    'error' => $gatewayResult->resultDesc,
], 400);
```

The gateway error detail was returned in the `error` field, but the frontend `catch` block only displayed `err.message` (the generic text), discarding the specific error.

### Confirmed by database evidence

Every failed payment had `failure_reason = "Phone number is required."` and empty `checkout_request_id`.

---

## Additional Issues Found & Fixed

### (a) Broken enum — `method` column
`database/migrations/2026_01_01_000006_create_payments_table.php` defined `method` as `enum('mpesa', 'card')`, but `config/payments.php` registers `airtel_money` and `equity` gateways too. Inserting those values would fail with "Data truncated".

**Fix:** New migration `2026_11_04_000001_convert_payment_method_to_string` converts `method` and `payment_method` from enum to string. Existing rows preserved.

### (c) Stale config / simulation mode
`.env` has `MPESA_SIMULATE=true` and `APP_DEBUG=true`. No config cache was present. Simulation mode works correctly — no credentials needed.

### (d) Retry logic
The original code blocked retries on ANY existing payment (even failed ones). Fixed to only block `completed` payments. Failed or voided payments now allow a new attempt.

### (e) Status endpoint ownership check (WRONG FIELD)
`PaymentController::status` compared `$request->user()?->id` (users.id) to `$payment->driver_id` (driver_profiles.id) — different values. Fixed to compare against `$payment->driver->user_id`.

### (e) Callback auth
`MpesaCallbackController::handle` at `Route::post('/mpesa/callback', ...)` is NOT behind `auth.jwt` — public. ✅
`PaymentCallbackController::handle` at `Route::post('/payments/callback/{provider}', ...)` is in the public group — not behind auth.jwt. ✅

### (f) `verify` endpoint lifted suspension
`PaymentController::verify` directly executed `$driver->update(['status' => 'active'])`, violating the rule that only `DriverStatusSynchronizer` may write `driver_profiles.status`. Also, a payment must NEVER lift a risk/points-based suspension.

**Fix:** Replaced direct status write with `DriverStatusSynchronizer::sync($driver->fresh())`, which derives status from active sanctions only.

### (f) Synchronous notifications in transaction
`NotificationService::notifyUser()` was called inside `DB::transaction` blocks. If mail/SMTP failed, the payment transaction could roll back.

**Fix:** Moved all notifications outside transactions, wrapped in try/catch, logged at warning level. They can never fail a payment now.

### (g) Frontend error display
The frontend `catch` block showed only `err.message` (generic), discarding the server's `error` field with the specific reason.

**Fix:** Frontend now checks for `failure_reason` / `result_desc` from the server response and displays the specific error.

### New issue found & fixed during testing
**`MpesaGateway::query()` checked `$payment->checkout_request_id` but `PaymentController::initiate` stored the reference in `provider_reference`.** The query always failed with "No checkout request ID."

**Fix:** `MpesaGateway::query()` now falls back to `provider_reference` when `checkout_request_id` is null.

---

## Files Changed

| File | Change |
|------|--------|
| `database/migrations/2026_11_04_000001_convert_payment_method_to_string.php` | **NEW** — Convert enum to string for method/payment_method |
| `app/Http/Controllers/PaymentController.php` | Fixed: phone validation, ownership check, verify (no suspension lift), notifications outside transaction, status endpoint ownership field, added payMpesa method |
| `app/Http/Controllers/Api/PaymentController.php` | Fixed: notification moved outside transaction |
| `app/Services/MpesaGateway.php` | Fixed: query() falls back to provider_reference |
| `app/Console/Commands/CleanupFailedPayments.php` | **NEW** — php artisan payments:cleanup-failed [--apply] |
| `apps/frontend/traffic-demerit-portal/src/api/appClient.js` | Added phone param to initiate(), added status() method |
| `apps/frontend/traffic-demerit-portal/src/pages/DriverDashboard.jsx` | Added phone input, polling status, server error display |
| `docs/payment-debug.md` | This document |

---

## Verification

**All 57 tests pass** (43 original + 14 new ML tests).  
**Frontend lint passes** (zero errors).  
**Frontend build succeeds.**

### End-to-end test results

Simulated payment for violation #824 (Mwangi Njeri, 3 pts, KES 1,500) with phone `0712345678`:
1. ✅ Phone validation passed
2. ✅ M-Pesa gateway simulate mode: STK Push accepted
3. ✅ Payment query: completed (result_code=0)
4. ✅ Payment status: completed
5. ✅ Violation status: confirmed
6. ✅ Driver status synced via DriverStatusSynchronizer (no suspension lift)

### Commands to run

```bash
# 1. Apply database migrations (including enum→string conversion)
php artisan migrate

# 2. Clear config cache (ensures MPESA_SIMULATE=true is active)
php artisan config:clear

# 3. Clean up stale/duplicate failed payments
php artisan payments:cleanup-failed --apply

# 4. Run tests
vendor\bin\phpunit

# 5. Restart services
php artisan serve          # Laravel (port 8000)
python -m uvicorn app:app --host 127.0.0.1 --port 8001  # ML service (port 8001)
```

### Demo credentials (for testing)

| Email | Password | Driver | Points | Status |
|-------|----------|--------|--------|--------|
| mwangi.njeri@example.com | Mwangi@2026 | Mwangi Njeri | 28 | Suspended |
| ngugi.wangechi@example.com | Ngugi@2026 | Ngugi Wangechi | 71 | Suspended |
| driver@ntsa.go.ke | Driver@123 | Demo Driver | - | - |

A suspended driver **can** log in and pay fines — only `is_active=false` on the users table blocks login. Payment completion never lifts a risk/points-based suspension.
