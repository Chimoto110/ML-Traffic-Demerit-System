<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficerAssignment extends Model
{
    protected $fillable = [
        'officer_user_id',
        'station_id',
        'badge_number',
        'shift_type',
        'assignment_start_date',
        'assignment_end_date',
    ];

    protected $casts = [
        'assignment_start_date' => 'date',
        'assignment_end_date' => 'date',
    ];

    public function officer()
    {
        return $this->belongsTo(User::class, 'officer_user_id');
    }

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function violations()
    {
        return $this->hasMany(Violation::class);
    }

    public function reviewLogs()
    {
        return $this->hasMany(ReviewLog::class);
    }
}
