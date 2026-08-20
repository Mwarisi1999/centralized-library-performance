<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'entry_code', 'user_id', 'project_id', 'task_id', 'subtask_id', 'work_date', 'work_location',
        'start_time', 'end_time', 'duration_minutes', 'work_description',
        'output_deliverable', 'challenge_encountered', 'corrective_action',
        'support_required', 'planned_next_activity', 'remarks',
    ];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'duration_minutes' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function subtask()
    {
        return $this->belongsTo(Subtask::class);
    }

    public function evidences()
    {
        return $this->hasMany(WorkEvidence::class);
    }

    public function activities()
    {
        return $this->hasMany(WorkEntryActivity::class);
    }

    public function isEvidenceEditable(): bool
    {
        $task = $this->task;

        return $task?->is_active === true
            && ! in_array($task->status, ['pending_review', 'completed', 'cancelled'], true);
    }

    public function scopeOverlapping(Builder $query, string $startTime, string $endTime): Builder
    {
        return $query
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);
    }

    public function getFormattedDurationAttribute(): string
    {
        return self::formatMinutes($this->duration_minutes);
    }

    public static function formatMinutes(int $minutes): string
    {
        $hours = $minutes / 60;

        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.').' '.($minutes === 60 ? 'hour' : 'hours');
    }
}
