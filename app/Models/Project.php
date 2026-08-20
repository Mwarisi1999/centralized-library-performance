<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    public const SCOPES = ['university_wide', 'selected_campuses'];

    public const STATUSES = ['planned', 'not_started', 'in_progress', 'on_hold', 'completed', 'cancelled'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const PROGRESS_METHODS = ['manual', 'tasks', 'milestones', 'deliverables'];

    protected $fillable = [
        'project_code', 'title', 'description', 'project_category_id', 'owner_id',
        'created_by', 'start_date', 'due_date', 'completed_at', 'scope',
        'priority_level', 'priority_score', 'progress_method', 'progress_percentage',
        'status', 'health_status', 'objectives', 'expected_deliverables', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'progress_percentage' => 'decimal:2',
            'priority_score' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(ProjectCategory::class, 'project_category_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campuses()
    {
        return $this->belongsToMany(Campus::class, 'project_campuses')->withTimestamps();
    }

    public function projectCampuses()
    {
        return $this->hasMany(ProjectCampus::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['project_role', 'joined_at', 'left_at', 'is_active'])
            ->withTimestamps();
    }

    public function projectMembers()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function workEntries()
    {
        return $this->hasMany(WorkEntry::class);
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

            return $query->where(function (Builder $query) use ($user, $campusId) {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('projectMembers', fn (Builder $query) => $query
                        ->where('user_id', $user->id)
                        ->where('is_active', true))
                    ->orWhere('scope', 'university_wide')
                    ->when($campusId, fn (Builder $query) => $query->orWhereHas(
                        'campuses', fn (Builder $query) => $query->whereKey($campusId)
                    ));
            });
        }

        return $query->whereHas('projectMembers', fn (Builder $query) => $query
            ->where('user_id', $user->id)
            ->where('is_active', true));
    }

    public static function label(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
