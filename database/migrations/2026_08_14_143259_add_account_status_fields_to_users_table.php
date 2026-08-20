<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_status', [
                'pending',
                'active',
                'suspended',
                'inactive',
            ])->default('pending')->after('password');

            $table->timestamp('activated_at')->nullable()->after('account_status');
            $table->timestamp('last_login_at')->nullable()->after('activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'account_status',
                'activated_at',
                'last_login_at',
            ]);
        });
    }
};
