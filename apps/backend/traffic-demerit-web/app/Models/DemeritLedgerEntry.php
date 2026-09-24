<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemeritLedgerEntry extends Model
{
    protected $fillable = [
        'driver_id', 'violation_id', 'points_applied', 'days_since_last_offence', 'running_balance', 'reason', 'reason_code',
    ];

    protected $casts = [
        'points_applied' => 'integer',
        'days_since_last_offence' => 'integer',
        'running_balance' => 'integer',
    ];

    public function driver()
    {
        return $this->belongsTo(DriverProfile::class, 'driver_id');
    }

    public function violation()
    {
        return $this->belongsTo(Violation::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'ledger_entry_id');
    }
}
