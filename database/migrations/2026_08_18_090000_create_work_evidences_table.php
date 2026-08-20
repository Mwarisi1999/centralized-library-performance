<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_evidences', function (Blueprint $table) {
            $table->id();
            $table->string('evidence_code')->unique();
            $table->foreignId('work_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 10);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('stored_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('file_extension', 20)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('url')->nullable();
            $table->timestamps();

            $table->index(['work_entry_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_evidences');
    }
};
