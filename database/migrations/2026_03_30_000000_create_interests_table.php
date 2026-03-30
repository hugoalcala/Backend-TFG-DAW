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
        // Tabla para almacenar los intereses disponibles en el sistema
        Schema::create('interests', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Ej: "Programación", "Diseño", "Marketing"
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Tabla pivote para relacionar usuarios con intereses (relación muchos a muchos)
        Schema::create('interest_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('interest_id')->constrained('interests')->onDelete('cascade');
            $table->timestamps();
            
            // Evitar duplicados: un usuario no puede tener el mismo interés dos veces
            $table->unique(['user_id', 'interest_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interest_user');
        Schema::dropIfExists('interests');
    }
};
