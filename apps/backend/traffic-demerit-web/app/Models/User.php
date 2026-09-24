<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'role_id', 'is_active', 'registered_on'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'registered_on' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function roleRecord()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'owner_user_id');
    }

    public function officerAssignments()
    {
        return $this->hasMany(OfficerAssignment::class, 'officer_user_id');
    }

    public function reviewLogs()
    {
        return $this->hasMany(ReviewLog::class, 'reviewed_by_user_id');
    }

    public function notifications()
    {
        return $this->hasMany(SystemNotification::class, 'recipient_user_id');
    }

    public function loginAudits()
    {
        return $this->hasMany(LoginAudit::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOfficer(): bool
    {
        return $this->role === 'officer';
    }

    public function isMotorist(): bool
    {
        return $this->role === 'motorist';
    }
}
