<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Station extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'station_name',
        'region',
        'physical_address',
        'contact_phone',
        'is_synthetic',
    ];

    public function officerAssignments()
    {
        return $this->hasMany(OfficerAssignment::class);
    }
}
