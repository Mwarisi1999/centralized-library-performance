<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->foreignId('reviewer_id')->nullable()->after('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->after('reviewer_id')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->timestamp('approved_at')->nullable()->after('reviewed_at');
            $table->timestamp('returned_at')->nullable()->after('approved_at');
            $table->text('approval_remark')->nullable()->after('returned_at');
            $table->text('correction_reason')->nullable()->after('approval_remark');
            $table->json('submitted_snapshot')->nullable()->after('correction_reason');
            $table->index(['reviewer_id', 'status', 'submitted_at']);
        });

        Schema::create('monthly_report_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('event', 50);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['monthly_report_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_report_activities');

        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->dropForeign(['submitted_by']);
            $table->dropIndex(['reviewer_id', 'status', 'submitted_at']);
            $table->dropColumn([
                'reviewer_id',
                'submitted_by',
                'submitted_at',
                'reviewed_at',
                'approved_at',
                'returned_at',
                'approval_remark',
                'correction_reason',
                'submitted_snapshot',
            ]);
        });
    }
};
