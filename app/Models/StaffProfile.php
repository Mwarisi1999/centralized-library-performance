<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'staff_number',
        'campus_id',
        'library_id',
        'position_id',
        'supervisor_id',
        'phone',
        'employment_type',
        'start_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function library()
    {
        return $this->belongsTo(Library::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
