<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    public const STATUSES = ['not_started', 'in_progress', 'pending_review', 'completed', 'deferred', 'cancelled'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    protected $fillable = [
        'task_code', 'project_id', 'title', 'description', 'created_by', 'assigned_by',
        'reviewer_id', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'returned_at',
        'start_date', 'due_date', 'priority', 'status', 'progress_percentage',
        'estimated_hours', 'completed_at', 'remarks', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'progress_percentage' => 'decimal:2',
            'estimated_hours' => 'decimal:2',
            'completed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'returned_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot(['assigned_at', 'is_active'])
            ->withTimestamps();
    }

    public function taskAssignees()
    {
        return $this->hasMany(TaskAssignee::class);
    }

    public function subtasks()
    {
        return $this->hasMany(Subtask::class);
    }

    public function activities()
    {
        return $this->hasMany(TaskActivity::class);
    }

    public function reviews()
    {
        return $this->hasMany(TaskReview::class);
    }

    public function workEntries()
    {
        return $this->hasMany(WorkEntry::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function hasAutomaticProgress(): bool
    {
        return $this->subtasks()->activeForProgress()->exists();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $permissions = $user->getAllPermissions()->pluck('name');

        if ($permissions->contains('view university dashboard')
            || $permissions->contains('view monitoring dashboard')) {
            return $query;
        }

        if ($permissions->contains('view campus dashboard')) {
            $campusId = $user->staffProfile?->campus_id;
            $superviseeIds = $user->supervisees()->pluck('user_id');

            return $query->where(function (Builder $query) use ($user, $campusId, $superviseeIds) {
                $query->where('created_by', $user->id)
                    ->orWhereHas('taskAssignees', fn (Builder $query) => $query
                        ->where('user_id', $user->id)->where('is_active', true))
                    ->when($superviseeIds->isNotEmpty(), fn (Builder $query) => $query->orWhereHas(
                        'taskAssignees', fn (Builder $query) => $query
                            ->whereIn('user_id', $superviseeIds)->where('is_active', true)
                    ))
                    ->orWhereHas('project', function (Builder $query) use ($campusId) {
                        $query->where('scope', 'university_wide')
                            ->when($campusId, fn (Builder $query) => $query->orWhereHas(
                                'campuses', fn (Builder $query) => $query->whereKey($campusId)
                            ));
                    });
            });
        }

        if ($permissions->contains('create tasks')) {
            return $query->where(function (Builder $query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhereHas('taskAssignees', fn (Builder $query) => $query
                        ->where('user_id', $user->id)->where('is_active', true))
                    ->orWhereHas('project.projectMembers', fn (Builder $query) => $query
                        ->where('user_id', $user->id)->where('is_active', true));
            });
        }

        return $query->whereHas('taskAssignees', fn (Builder $query) => $query
            ->where('user_id', $user->id)->where('is_active', true));
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date?->isBefore(today())
            && ! in_array($this->status, ['completed', 'cancelled'], true);
    }

    public static function label(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
