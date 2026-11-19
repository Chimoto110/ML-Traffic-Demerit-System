<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_user_id',
        'plate_number',
        'make_model',
        'vehicle_class',
        'colour',
        'year_of_manufacture',
        'registration_expiry',
        'is_synthetic',
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
