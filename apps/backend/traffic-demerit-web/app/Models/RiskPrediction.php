<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskPrediction extends Model
{
    protected $fillable = [
        'driver_id',
        'risk_score',
        'risk_class',
        'model_version',
        'confidence_level',
        'predicted_at',
    ];

    protected $casts = [
        'predicted_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(DriverProfile::class, 'driver_id');
    }

    public function assessments()
    {
        return $this->hasMany(RiskAssessment::class);
    }

    public function sanctions()
    {
        return $this->hasMany(SanctionAction::class);
    }
}
