<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    protected $fillable = [
        'user_id', 'license_no', 'full_name', 'profile_photo_path', 'license_issue_date', 'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function violations()
    {
        return $this->hasMany(Violation::class, 'driver_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(DemeritLedgerEntry::class, 'driver_id');
    }

    public function riskAssessments()
    {
        return $this->hasMany(RiskAssessment::class, 'driver_id');
    }

    public function riskPredictions()
    {
        return $this->hasMany(RiskPrediction::class, 'driver_id');
    }

    public function sanctionActions()
    {
        return $this->hasMany(SanctionAction::class, 'driver_id');
    }

    public function appeals()
    {
        return $this->hasMany(Appeal::class, 'driver_id');
    }

    public function currentBalance(): int
    {
        return (int) $this->ledgerEntries()->latest('id')->value('running_balance') ?? 0;
    }

    public function latestRiskAssessment()
    {
        return $this->riskAssessments()->latest('generated_at')->first();
    }
}
