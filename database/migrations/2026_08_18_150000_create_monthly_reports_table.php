<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('reporting_month');
            $table->unsignedSmallInteger('reporting_year');
            $table->string('status', 30)->default('draft');
            $table->text('key_achievements')->nullable();
            $table->text('challenges')->nullable();
            $table->text('corrective_actions')->nullable();
            $table->text('support_required')->nullable();
            $table->text('planned_activities_next_month')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'reporting_month', 'reporting_year']);
            $table->index(['reporting_year', 'reporting_month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_reports');
    }
};
