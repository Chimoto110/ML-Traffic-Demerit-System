<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentEvent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'payment_id', 'event_type', 'old_status', 'new_status',
        'changed_by', 'failure_code', 'failure_reason', 'receipt', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
