<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('reviewer_id')->nullable()->after('assigned_by')->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->after('reviewer_id')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->foreignId('reviewed_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->timestamp('returned_at')->nullable()->after('reviewed_at');
            $table->index(['reviewer_id', 'status']);
        });

        Schema::create('task_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->index(['reviewer_id', 'status', 'submitted_at']);
        });

        $pendingTasks = DB::table('tasks')->where('status', 'pending_review')->whereNull('deleted_at')->get();
        foreach ($pendingTasks as $task) {
            $assigneeIds = DB::table('task_assignees')->where('task_id', $task->id)->where('is_active', true)->orderBy('id')->pluck('user_id')->map(fn ($id) => (int) $id);
            $responsibleId = $assigneeIds->first();
            $reviewerId = $this->resolveReviewer($responsibleId, $assigneeIds->all());
            if (! $reviewerId) {
                continue;
            }

            $submission = DB::table('task_activities')->where('task_id', $task->id)->where('activity_type', 'submitted_for_review')->latest('id')->first();
            $submittedBy = $submission?->user_id ?: $task->created_by;
            $submittedAt = $submission?->created_at ?: $task->updated_at;
            DB::table('tasks')->where('id', $task->id)->update(['reviewer_id' => $reviewerId, 'submitted_by' => $submittedBy, 'submitted_at' => $submittedAt]);
            DB::table('task_reviews')->insert(['task_id' => $task->id, 'submitted_by' => $submittedBy, 'reviewer_id' => $reviewerId, 'submitted_at' => $submittedAt, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function resolveReviewer(?int $userId, array $assigneeIds): ?int
    {
        $visited = [];
        while ($userId && ! in_array($userId, $visited, true)) {
            $visited[] = $userId;
            $supervisorId = DB::table('staff_profiles')->where('user_id', $userId)->whereNull('deleted_at')->value('supervisor_id');
            if (! $supervisorId) {
                return null;
            }
            $active = DB::table('users')->where('id', $supervisorId)->where('account_status', 'active')->exists();
            if ($active && ! in_array((int) $supervisorId, $assigneeIds, true)) {
                return (int) $supervisorId;
            }
            $userId = (int) $supervisorId;
        }

        return null;
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reviews');
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['reviewer_id', 'status']);
            $table->dropColumn(['reviewer_id', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'returned_at']);
        });
    }
};
