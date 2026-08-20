<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectCampus extends Model
{
    protected $fillable = ['project_id', 'campus_id'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }
}
