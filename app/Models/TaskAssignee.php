<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskAssignee extends Model
{
    protected $fillable = ['task_id', 'user_id', 'assigned_at', 'is_active'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'is_active' => 'boolean'];
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
