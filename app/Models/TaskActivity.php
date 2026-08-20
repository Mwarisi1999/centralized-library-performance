<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskActivity extends Model
{
    protected $fillable = ['task_id', 'user_id', 'activity_type', 'message', 'old_status', 'new_status', 'old_progress', 'new_progress'];

    protected function casts(): array
    {
        return ['old_progress' => 'decimal:2', 'new_progress' => 'decimal:2'];
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
