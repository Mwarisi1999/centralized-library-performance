<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('progress_percentage', 5, 2)->default(0)->change();
        });

        $project = DB::table('projects')->where('project_code', 'PRJ-2026-0001')->first();
        if (! $project) {
            return;
        }

        $tasks = DB::table('tasks')
            ->where('project_id', $project->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->get(['status', 'progress_percentage']);

        $progress = $tasks->isEmpty() ? 0 : round((float) $tasks->avg('progress_percentage'), 2);
        $allCompleted = $tasks->isNotEmpty() && $tasks->every(fn ($task) => $task->status === 'completed');
        $started = $tasks->contains(fn ($task) => (float) $task->progress_percentage > 0
            || in_array($task->status, ['in_progress', 'pending_review'], true));
        $status = $allCompleted ? 'completed' : ($started ? 'in_progress' : ($project->status === 'planned' ? 'planned' : 'not_started'));

        DB::table('projects')->where('id', $project->id)->update([
            'progress_method' => 'tasks',
            'progress_percentage' => $allCompleted ? 100 : $progress,
            'status' => $status,
            'completed_at' => $allCompleted ? now() : null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('projects')->where('project_code', 'PRJ-2026-0001')->where('progress_method', 'tasks')->update(['progress_method' => 'manual']);
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percentage')->default(0)->change();
        });
    }
};
