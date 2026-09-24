<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Violation extends Model
{
    protected $fillable = [
        'driver_id', 'vehicle_id', 'officer_id', 'officer_assignment_id', 'offense_type', 'speed_recorded',
        'posted_speed_limit', 'zone_type', 'weather_conditions', 'location', 'evidence_reference', 'points_assigned', 'status', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(DriverProfile::class, 'driver_id');
    }

    public function officer()
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function officerAssignment()
    {
        return $this->belongsTo(OfficerAssignment::class);
    }

    public function ledgerEntry()
    {
        return $this->hasOne(DemeritLedgerEntry::class, 'violation_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'violation_id');
    }
}
