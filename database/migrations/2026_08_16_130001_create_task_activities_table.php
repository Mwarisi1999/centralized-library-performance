<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('activity_type', 50);
            $table->text('message')->nullable();
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->decimal('old_progress', 5, 2)->nullable();
            $table->decimal('new_progress', 5, 2)->nullable();
            $table->timestamps();
            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
    }
};
