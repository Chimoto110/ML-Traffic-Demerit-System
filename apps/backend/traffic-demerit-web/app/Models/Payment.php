<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'ledger_entry_id', 'violation_id', 'driver_id', 'amount', 'amount_paid', 'method', 'payment_method',
        'status', 'transaction_ref', 'reference_number', 'currency', 'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
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
}
