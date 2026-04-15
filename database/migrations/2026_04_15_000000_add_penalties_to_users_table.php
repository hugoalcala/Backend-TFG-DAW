<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Campo para contar penalizaciones/strikes
            $table->unsignedInteger('penalties')->default(0)->after('avatar_path');
            // Campo para razón de la última penalización
            $table->string('last_penalty_reason')->nullable()->after('penalties');
            // Timestamp de la última penalización
            $table->timestamp('last_penalty_at')->nullable()->after('last_penalty_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['penalties', 'last_penalty_reason', 'last_penalty_at']);
        });
    }
};
