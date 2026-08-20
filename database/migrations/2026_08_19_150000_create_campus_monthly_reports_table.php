<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campus_monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code')->unique();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('reporting_month');
            $table->unsignedSmallInteger('reporting_year');
            $table->string('status', 30)->default('finalized');
            $table->foreignId('finalized_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at');
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(
                ['campus_id', 'reporting_month', 'reporting_year'],
                'cmr_campus_period_unique'
            );

            $table->index(
                ['reporting_year', 'reporting_month', 'status'],
                'cmr_period_status_idx'
            );
        });

        Schema::create('campus_monthly_report_activities', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('campus_monthly_report_id');
            $table->unsignedBigInteger('user_id');

            $table->string('event', 50);
            $table->text('description');
            $table->timestamps();

            $table->foreign(
                'campus_monthly_report_id',
                'cmra_report_fk'
            )->references('id')
                ->on('campus_monthly_reports')
                ->cascadeOnDelete();

            $table->foreign(
                'user_id',
                'cmra_user_fk'
            )->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campus_monthly_report_activities');
        Schema::dropIfExists('campus_monthly_reports');
    }
};
