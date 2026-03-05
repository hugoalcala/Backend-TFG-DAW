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
            $table->enum('teacher_status', ['pending', 'approved', 'rejected'])->nullable()->after('role');
            $table->string('subject')->nullable()->after('teacher_status');
            $table->text('bio')->nullable()->after('subject');
            $table->decimal('price_per_hour', 8, 2)->nullable()->after('bio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['teacher_status', 'subject', 'bio', 'price_per_hour']);
        });
    }
};
