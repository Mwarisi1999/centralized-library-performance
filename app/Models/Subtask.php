<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subtask extends Model
{
    use SoftDeletes;

    public const STATUSES = ['not_started', 'in_progress', 'completed', 'deferred', 'cancelled'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    protected $fillable = ['subtask_code', 'task_id', 'title', 'description', 'created_by', 'assigned_to', 'start_date', 'due_date', 'priority', 'status', 'progress_percentage', 'estimated_hours', 'completed_at', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'due_date' => 'date', 'progress_percentage' => 'decimal:2', 'estimated_hours' => 'decimal:2', 'completed_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function workEntries()
    {
        return $this->hasMany(WorkEntry::class);
    }

    public function scopeActiveForProgress(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', '!=', 'cancelled');
    }

    public static function label(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
