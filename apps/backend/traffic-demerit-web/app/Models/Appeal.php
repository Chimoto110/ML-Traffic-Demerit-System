<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appeal extends Model
{
    protected $fillable = [
        'driver_id',
        'violation_id',
        'sanction_action_id',
        'reason',
        'status',
        'outcome',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'resolved_at',
        'submitted_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'resolved_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(DriverProfile::class, 'driver_id');
    }

    public function violation()
    {
        return $this->belongsTo(Violation::class, 'violation_id');
    }

    public function sanction()
    {
        return $this->belongsTo(SanctionAction::class, 'sanction_action_id');
    }
}
