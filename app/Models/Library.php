<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Library extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'campus_id',
        'name',
        'code',
        'email',
        'phone',
        'is_active',
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class);
    }
}
