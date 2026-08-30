<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_entries', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('work_date');
            $table->string('priority', 20)->default('medium')->after('due_date');
            $table->string('activity_status', 30)->default('in_progress')->after('priority');
            $table->index(['user_id', 'activity_status']);
        });
    }

    public function down(): void
    {
        Schema::table('work_entries', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'activity_status']);
            $table->dropColumn(['due_date', 'priority', 'activity_status']);
        });
    }
};
