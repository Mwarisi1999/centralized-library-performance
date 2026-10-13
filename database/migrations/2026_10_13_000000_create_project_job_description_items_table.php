<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_job_description_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_job_detail_id')->nullable()->constrained()->nullOnDelete();
            $table->text('duty_text');
            $table->boolean('is_other')->default(false);
            $table->timestamps();

            $table->index(['project_id', 'is_other']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_job_description_items');
    }
};
