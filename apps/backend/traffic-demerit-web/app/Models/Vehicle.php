<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'owner_user_id',
        'plate_number',
        'make_model',
        'vehicle_class',
        'colour',
        'year_of_manufacture',
        'registration_expiry',
    ];

    protected $casts = [
        'registration_expiry' => 'date',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function violations()
    {
        return $this->hasMany(Violation::class);
    }
}
