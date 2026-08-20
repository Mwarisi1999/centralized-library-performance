<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_entries', function (Blueprint $table) {
            $table->text('challenge_encountered')->nullable()->after('output_deliverable');
            $table->text('corrective_action')->nullable()->after('challenge_encountered');
            $table->text('support_required')->nullable()->after('corrective_action');
            $table->text('planned_next_activity')->nullable()->after('support_required');
        });
    }

    public function down(): void
    {
        Schema::table('work_entries', function (Blueprint $table) {
            $table->dropColumn([
                'challenge_encountered',
                'corrective_action',
                'support_required',
                'planned_next_activity',
            ]);
        });
    }
};
