<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->string('gender', 30)->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('address', 500)->nullable()->after('date_of_birth');
            $table->string('emergency_contact_name')->nullable()->after('address');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_phone');
            $table->string('profile_photo_path')->nullable()->after('emergency_contact_relationship');
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'date_of_birth',
                'address',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relationship',
                'profile_photo_path',
            ]);
        });
    }
};
