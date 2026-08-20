<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campus extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'location',
        'email',
        'phone',
        'is_active',
    ];

    public function libraries()
    {
        return $this->hasMany(Library::class);
    }

    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class);
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_campuses')->withTimestamps();
    }

    public function monthlyReports()
    {
        return $this->hasMany(CampusMonthlyReport::class);
    }
}
