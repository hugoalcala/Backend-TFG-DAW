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
        Schema::create('rating_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rating_id');
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('reported_by_user_id');
            $table->string('reason'); // offensive_content, spam, fake_review, inappropriate, other
            $table->text('details')->nullable();
            $table->string('status')->default('pending'); // pending, reviewed, resolved, dismissed
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            
            // Relaciones
            $table->foreign('rating_id')->references('id')->on('ratings')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reported_by_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by_user_id')->references('id')->on('users')->onDelete('set null');
            
            // Índices
            $table->index('rating_id');
            $table->index('teacher_id');
            $table->index('reported_by_user_id');
            $table->index('status');
            
            // Constraint único: un reporte por usuario/reseña
            $table->unique(['rating_id', 'reported_by_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rating_reports');
    }
};
