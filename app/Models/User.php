<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'account_status',
        'activated_at',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'activated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function supervisees()
    {
        return $this->hasMany(StaffProfile::class, 'supervisor_id');
    }

    public function activationTokens()
    {
        return $this->hasMany(AccountActivationToken::class);
    }

    public function ownedProjects()
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function createdProjects()
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot(['project_role', 'joined_at', 'left_at', 'is_active'])
            ->withTimestamps();
    }

    public function projectMemberships()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function createdTasks()
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function assignedTasks()
    {
        return $this->belongsToMany(Task::class, 'task_assignees')
            ->withPivot(['assigned_at', 'is_active'])
            ->withTimestamps();
    }

    public function createdSubtasks()
    {
        return $this->hasMany(Subtask::class, 'created_by');
    }

    public function assignedSubtasks()
    {
        return $this->hasMany(Subtask::class, 'assigned_to');
    }

    public function taskActivities()
    {
        return $this->hasMany(TaskActivity::class);
    }

    public function tasksToReview()
    {
        return $this->hasMany(Task::class, 'reviewer_id');
    }

    public function submittedTaskReviews()
    {
        return $this->hasMany(TaskReview::class, 'submitted_by');
    }

    public function assignedTaskReviews()
    {
        return $this->hasMany(TaskReview::class, 'reviewer_id');
    }

    public function workEntries()
    {
        return $this->hasMany(WorkEntry::class);
    }

    public function workEvidences()
    {
        return $this->hasMany(WorkEvidence::class);
    }

    public function workEntryActivities()
    {
        return $this->hasMany(WorkEntryActivity::class);
    }

    public function monthlyReports()
    {
        return $this->hasMany(MonthlyReport::class);
    }

    public function monthlyReportsToReview()
    {
        return $this->hasMany(MonthlyReport::class, 'reviewer_id');
    }

    public function monthlyReportActivities()
    {
        return $this->hasMany(MonthlyReportActivity::class);
    }

    public function finalizedCampusMonthlyReports()
    {
        return $this->hasMany(CampusMonthlyReport::class, 'finalized_by');
    }
}
