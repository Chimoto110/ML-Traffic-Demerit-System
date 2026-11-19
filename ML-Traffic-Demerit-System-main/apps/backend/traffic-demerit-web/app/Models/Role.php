<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'role_name',
        'permission_level',
        'role_description',
        'is_synthetic',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
