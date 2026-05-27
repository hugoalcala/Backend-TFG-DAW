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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            
            // Información de la contratación
            $table->integer('hours')->default(1)->comment('Número de horas contratadas');
            $table->decimal('price_per_hour', 10, 2)->comment('Precio por hora al momento de la contratación');
            $table->decimal('total_amount', 10, 2)->comment('Monto total de la contratación');
            
            // Información del pago
            $table->string('stripe_payment_intent_id')->nullable()->unique()->comment('ID del payment intent de Stripe');
            $table->string('stripe_charge_id')->nullable()->unique()->comment('ID del charge de Stripe');
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            
            // Estado de la contratación
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Fechas de sesiones (si es relevante)
            $table->timestamp('scheduled_start_date')->nullable();
            $table->timestamp('scheduled_end_date')->nullable();
            
            // Notas y comentarios
            $table->text('notes')->nullable()->comment('Notas adicionales sobre la contratación');
            
            $table->timestamps();
            
            // Índices para búsquedas rápidas
            $table->index('student_id');
            $table->index('teacher_id');
            $table->index('payment_status');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
