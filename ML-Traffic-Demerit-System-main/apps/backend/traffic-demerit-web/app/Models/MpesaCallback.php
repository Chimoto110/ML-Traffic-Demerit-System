<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpesaCallback extends Model
{
    protected $fillable = [
        'payment_id', 'checkout_request_id', 'merchant_request_id',
        'payload', 'source_ip', 'signature_ok', 'ip_ok',
        'processed_at', 'processing_result',
    ];

    protected $casts = [
        'payload' => 'array',
        'signature_ok' => 'boolean',
        'ip_ok' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
