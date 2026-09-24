<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskAssessment extends Model
{
    protected $fillable = [
        'driver_id', 'risk_prediction_id', 'risk_score', 'risk_class', 'model_version',
        'feature_snapshot', 'generated_at',
    ];

    protected $casts = [
        'feature_snapshot' => 'array',
        'generated_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(DriverProfile::class, 'driver_id');
    }

    public function sanctionActions()
    {
        return $this->hasMany(SanctionAction::class);
    }

    public function riskPrediction()
    {
        return $this->belongsTo(RiskPrediction::class, 'risk_prediction_id');
    }
}
