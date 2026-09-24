<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    protected $fillable = [
        'station_name',
        'region',
        'physical_address',
        'contact_phone',
    ];

    public function officerAssignments()
    {
        return $this->hasMany(OfficerAssignment::class);
    }
}
