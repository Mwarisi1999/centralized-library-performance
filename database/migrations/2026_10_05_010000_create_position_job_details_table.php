<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_job_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('salary_scale', 50)->nullable();
            $table->string('reports_to')->nullable();
            $table->string('responsible_for')->nullable();
            $table->text('job_purpose');
            $table->json('duties');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_job_details');
    }
};
