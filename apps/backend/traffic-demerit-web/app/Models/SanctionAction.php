<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SanctionAction extends Model
{
    protected $fillable = [
        'driver_id', 'risk_assessment_id', 'risk_prediction_id', 'trigger_reason',
        'action_type', 'sanction_type', 'fine_amount', 'effective_from', 'effective_to', 'issued_at',
        'status', 'lifted_by', 'lifted_at', 'webhook_status', 'webhook_response',
    ];

    protected $casts = [
        'lifted_at' => 'datetime',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'issued_at' => 'datetime',
        'webhook_response' => 'array',
    ];

    public function driver()
    {
        return $this->belongsTo(DriverProfile::class, 'driver_id');
    }

    public function riskAssessment()
    {
        return $this->belongsTo(RiskAssessment::class);
    }

    public function riskPrediction()
    {
        return $this->belongsTo(RiskPrediction::class);
    }

    public function reviewLogs()
    {
        return $this->hasMany(ReviewLog::class, 'sanction_action_id');
    }
}
