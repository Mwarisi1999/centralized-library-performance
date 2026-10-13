<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectJobDescriptionItem extends Model
{
    protected $fillable = [
        'project_id',
        'position_job_detail_id',
        'duty_text',
        'is_other',
    ];

    protected function casts(): array
    {
        return [
            'is_other' => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function positionJobDetail()
    {
        return $this->belongsTo(PositionJobDetail::class);
    }
}
