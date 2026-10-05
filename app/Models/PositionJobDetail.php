<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionJobDetail extends Model
{
    protected $fillable = [
        'position_id',
        'salary_scale',
        'reports_to',
        'responsible_for',
        'job_purpose',
        'duties',
    ];

    protected function casts(): array
    {
        return [
            'duties' => 'array',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
