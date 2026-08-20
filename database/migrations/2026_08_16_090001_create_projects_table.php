<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('project_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('start_date');
            $table->date('due_date');
            $table->timestamp('completed_at')->nullable();
            $table->string('scope', 30);
            $table->string('priority_level', 20);
            $table->unsignedSmallInteger('priority_score')->nullable();
            $table->string('progress_method', 30);
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->string('status', 30)->default('planned');
            $table->string('health_status', 30)->nullable();
            $table->text('objectives')->nullable();
            $table->text('expected_deliverables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority_level']);
            $table->index(['owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
