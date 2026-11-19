# M-Pesa (Daraja) Setup Guide

**Status:** Build complete. Credentials and go-live approval must be obtained by you.

---

## Prerequisites

You must obtain the following yourself (cannot be committed or provided):

| Item | Where to Get It | Notes |
|------|----------------|-------|
| Daraja consumer key | Safaricom Developer Portal → Dashboard → My Apps | Required for real mode |
| Daraja consumer secret | Safaricom Developer Portal → Dashboard → My Apps | Required for real mode |
| Shortcode | Safaricom Developer Portal (sandbox: `174379`) | Paybill or Till number |
| Passkey | Safaricom Developer Portal → SDK credentials | Required for real mode |
| Public HTTPS callback URL | ngrok, cloudflared, or your domain | Must be publicly reachable by Safaricom |
| Go-live approval | Safaricom Developer Portal → Apply for production | Required before switching to `MPESA_ENV=production` |

---

## Step 1: Create a Daraja Account and Sandbox App

1. Go to [developer.safaricom.co.ke](https://developer.safaricom.co.ke/)
2. Register an account and verify your email
3. Create a new app with the **Lipa Na M-Pesa Online (STK Push)** product
4. Copy the **Consumer Key** and **Consumer Secret** from the app dashboard
5. Copy the **sandbox test shortcode** and **passkey** shown on the Daraja simulator page
   - Sandbox shortcode: typically `174379`
   - Sandbox passkey: shown on the simulator page

---

## Step 2: Configure Environment Variables

Copy `.env.example` to `.env` and fill in the values:

```bash
cp .env.example .env
```

Edit `.env` with your values:

```ini
# M-Pesa / Daraja Configuration
MPESA_ENV=sandbox          # Change to 'production' after go-live
MPESA_SIMULATE=true        # Set to 'false' for real sandbox/production calls
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_SHORTCODE=174379     # Sandbox test shortcode
MPESA_PASSKEY=your_passkey
MPESA_TRANSACTION_TYPE=CustomerPayBillOnline
MPESA_PARTY_B=             # Till number if different from shortcode
MPESA_CALLBACK_BASE_URL=https://your-public-domain.com
MPESA_CALLBACK_TOKEN=a-long-random-secret-string
MPESA_TIMEOUT_SECONDS=120

# Queue driver (required for ShouldQueue notifications)
QUEUE_CONNECTION=database
```

**Never commit `.env` with real credentials.** The `.env.example` file contains only placeholders.

---

## Step 3: Expose Laravel Publicly with HTTPS

Daraja callbacks require a public HTTPS URL. During development:

### Option A: ngrok
```bash
ngrok http 8000
```
Use the `https://` URL as `MPESA_CALLBACK_BASE_URL`.

### Option B: cloudflared
```bash
cloudflared tunnel --url http://localhost:8000
```

---

## Step 4: Run Migrations and Clear Config

```bash
cd apps/backend/traffic-demerit-web

php artisan migrate
php artisan config:clear
```

This creates:
- `payments` table (with M-Pesa columns: merchant_request_id, checkout_request_id, mpesa_receipt, etc.)
- `mpesa_callbacks` table (audit log of every callback)
- `payment_events` table (audit trail of state changes)

---

## Step 5: Run the Full Payment Flow in Simulation Mode

With `MPESA_SIMULATE=true`, no credentials or network calls are needed:

1. Start Laravel: `php artisan serve`
2. Start the queue worker: `php artisan queue:work`
3. Start the scheduler: `php artisan schedule:work`
4. Log in as a driver (e.g., `mwangi.njeri@example.com` / `Mwangi@2026`)
5. Go to the driver portal, select an unpaid violation, click "Pay now"
6. Enter a phone number:
   - `...0001` → Success (payment completes)
   - `...0002` → Insufficient funds
   - `...0003` → Cancelled by user
   - `...0004` → Timeout
7. Verify the payment row, notification, and receipt

### Expected Database Rows After Successful Payment

**`payments` table:**
| id | violation_id | method | status | amount | mpesa_receipt | failure_code |
|----|-------------|--------|--------|--------|---------------|--------------|
| 1  | 824         | mpesa  | completed | 1500.00 | REC-xxx | NULL |

**`mpesa_callbacks` table:**
| id | payment_id | checkout_request_id | processing_result |
|----|-----------|---------------------|-------------------|
| 1  | 1         | CO-xxx              | completed         |

**`payment_events` table:**
| id | payment_id | event_type | old_status | new_status | changed_by |
|----|-----------|------------|------------|------------|------------|
| 1  | 1         | initiated  | NULL       | initiated  | user       |
| 2  | 1         | pending    | initiated  | pending    | gateway    |
| 3  | 1         | completed  | pending    | completed  | callback   |

---

## Step 6: Test Against Real Sandbox (Optional)

If you have sandbox credentials:

1. Set `MPESA_SIMULATE=false` in `.env`
2. Fill in `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_PASSKEY`
3. Set `MPESA_CALLBACK_BASE_URL` to your public HTTPS URL (from Step 3)
4. Set `MPESA_CALLBACK_TOKEN` to a random secret
5. Run `php artisan config:clear`
6. Use the Daraja sandbox test phone number from the portal
7. Run a full payment and verify the callback is received

---

## Step 7: Go-Live Checklist (Production)

Before switching to production, you MUST:

- [ ] Obtain a production shortcode (Paybill or Till) from Safaricom
- [ ] Submit a go-live request on the Daraja portal
- [ ] Obtain production consumer key/secret/passkey
- [ ] Set up a public HTTPS domain with a valid SSL certificate
- [ ] Set `MPESA_ENV=production` and `MPESA_SIMULATE=false`
- [ ] Set production credentials in `.env`
- [ ] Enable IP allow-list (optional but recommended)
- [ ] Start the scheduler: `php artisan schedule:work` (or cron every minute)
- [ ] Start the queue worker: `php artisan queue:work`
- [ ] Monitor `mpesa_callbacks` and `payment_events` tables for anomalies

---

## Commands to Run

```bash
# 1. Apply database migrations
php artisan migrate

# 2. Clear config cache (picks up new .env values)
php artisan config:clear

# 3. Run reconciliation (scheduled every 2 minutes, or manually)
php artisan mpesa:reconcile
php artisan mpesa:reconcile --dry-run   # Test without changes

# 4. Clean up stale payments
php artisan payments:cleanup-failed --apply

# 5. Run tests
vendor\bin\phpunit

# 6. Start queue worker (for ShouldQueue notifications)
php artisan queue:work

# 7. Start scheduler (for reconcile command)
php artisan schedule:work

# 8. Start Laravel server
php artisan serve
```

---

## Payment State Machine

```
initiated ──▶ pending ──▶ completed
              │           │
              │           └─▶ (payment never lifts suspension)
              │
              └─▶ failed
              │           │
              └─▶ expired
```

States:
- **initiated**: Payment row created, STK push not yet sent
- **pending**: STK push sent to phone, waiting for user to enter PIN
- **completed**: Payment confirmed, violation settled
- **failed**: Payment declined (insufficient funds, cancelled, etc.)
- **expired**: No callback received within timeout window

---

## Files Created/Changed

| File | Description |
|------|-------------|
| `config/mpesa.php` | Updated with all Daraja env vars |
| `.env.example` | Added M-Pesa placeholder variables |
| `app/Services/Mpesa/DarajaClient.php` | **NEW** - Daraja OAuth + STK push client |
| `app/Services/Mpesa/PaymentCompletionService.php` | **NEW** - Single place for completing payments |
| `app/Services/MpesaGateway.php` | Updated to use DarajaClient |
| `app/Http/Controllers/MpesaCallbackController.php` | Rewritten with token verification + callback logging |
| `app/Http/Controllers/PaymentController.php` | Rewritten with state machine + idempotency |
| `app/Models/Payment.php` | Updated fillable + helper methods |
| `app/Models/PaymentEvent.php` | **NEW** - Audit trail model |
| `app/Models/MpesaCallback.php` | **NEW** - Callback audit model |
| `database/migrations/2026_11_04_000002_add_mpesa_payment_columns.php` | **NEW** - initiated_at, completed_at, expires_at, etc. |
| `database/migrations/2026_11_04_000003_create_mpesa_callbacks_table.php` | **NEW** - Callback audit log |
| `database/migrations/2026_11_04_000004_create_payment_events_table.php` | **NEW** - State change audit trail |
| `app/Console/Commands/ReconcileMpesa.php` | **NEW** - Pending payment reconciliation |
| `tests/Feature/MpesaPaymentTest.php` | **NEW** - 28 feature tests |
| `docs/mpesa-setup.md` | **NEW** - This document |
