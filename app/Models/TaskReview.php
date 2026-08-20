<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskReview extends Model
{
    public const STATUSES = ['pending', 'approved', 'returned'];

    protected $fillable = ['task_id', 'submitted_by', 'reviewer_id', 'submitted_at', 'status', 'reviewed_by', 'reviewed_at', 'remark'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
