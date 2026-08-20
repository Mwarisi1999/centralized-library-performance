<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->decimal('progress_percentage', 5, 2)->default(0)->change());
        Schema::create('subtasks', function (Blueprint $table) {
            $table->id();
            $table->string('subtask_code')->unique();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 30)->default('not_started');
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['task_id', 'is_active', 'status']);
            $table->index(['assigned_to', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtasks');
        Schema::table('tasks', fn (Blueprint $table) => $table->unsignedTinyInteger('progress_percentage')->default(0)->change());
    }
};
