<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_code')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('task_id')->constrained()->restrictOnDelete();
            $table->foreignId('subtask_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('duration_minutes');
            $table->text('work_description');
            $table->text('output_deliverable')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'work_date']);
            $table->index(['project_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_entries');
    }
};
