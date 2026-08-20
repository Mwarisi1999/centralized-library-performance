<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class);
    }
}
