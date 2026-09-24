<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewLog extends Model
{
    protected $fillable = [
        'sanction_action_id',
        'officer_assignment_id',
        'reviewed_by_user_id',
        'decision',
        'notes',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function sanction()
    {
        return $this->belongsTo(SanctionAction::class, 'sanction_action_id');
    }

    public function officerAssignment()
    {
        return $this->belongsTo(OfficerAssignment::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
