<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ledger_entry_id', 'violation_id', 'driver_id', 'amount', 'amount_paid',
        'method', 'payment_method', 'status', 'transaction_ref',
        'reference_number', 'currency', 'paid_at', 'is_synthetic',
        'card_brand', 'card_last4', 'provider_reference',
        'failure_reason', 'failure_code', 'idempotency_key',
        'merchant_request_id', 'checkout_request_id', 'mpesa_receipt',
        'result_code', 'result_desc', 'phone',
        'initiated_at', 'completed_at', 'expires_at', 'environment',
        'raw_callback',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'raw_callback' => 'array',
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    /**
     * Get all M-Pesa-specific columns that should be fillable.
     * Stored separately from $fillable for documentation/reference.
     */
    public const MPESA_FILLABLE = [
        'merchant_request_id', 'checkout_request_id', 'mpesa_receipt',
        'result_code', 'result_desc', 'phone', 'initiated_at',
        'completed_at', 'expires_at', 'failure_code', 'environment',
        'raw_callback',
    ];

    public function violation()
    {
        return $this->belongsTo(Violation::class);
    }

    public function ledgerEntry()
    {
        return $this->belongsTo(DemeritLedgerEntry::class, 'ledger_entry_id');
    }

    public function driver()
    {
        return $this->belongsTo(DriverProfile::class, 'driver_id');
    }

    /**
     * Check if the payment is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'voided', 'expired'], true);
    }

    /**
     * Check if the payment can still be retried (failed or expired).
     */
    public function isRetryable(): bool
    {
        return in_array($this->status, ['failed', 'expired'], true);
    }

    /**
     * Check if the payment has expired based on expires_at.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get the seconds remaining until expiry.
     */
    public function secondsRemaining(): ?int
    {
        if (!$this->expires_at || $this->isTerminal()) {
            return null;
        }

        $seconds = $this->expires_at->diffInSeconds(now());
        return max(0, $seconds);
    }

    /**
     * Record a payment event for audit trail.
     */
    public function recordEvent(
        string $eventType,
        ?string $oldStatus = null,
        ?string $changedBy = null,
        ?string $failureCode = null,
        ?string $failureReason = null,
        ?string $receipt = null,
        array $metadata = []
    ): void {
        $this->events()->create([
            'event_type' => $eventType,
            'old_status' => $oldStatus,
            'new_status' => $this->status,
            'changed_by' => $changedBy,
            'failure_code' => $failureCode,
            'failure_reason' => $failureReason,
            'receipt' => $receipt,
            'metadata' => $metadata,
        ]);
    }

    public function events()
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function callbacks()
    {
        return $this->hasMany(MpesaCallback::class);
    }
}
