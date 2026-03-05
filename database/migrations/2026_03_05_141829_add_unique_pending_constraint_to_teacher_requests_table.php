<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // Para SQLite y PostgreSQL: crear índice único parcial
        if ($driver === 'sqlite') {
            DB::statement(
                'CREATE UNIQUE INDEX teacher_requests_user_id_pending_unique 
                ON teacher_requests (user_id) 
                WHERE status = \'pending\''
            );
        } elseif ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX teacher_requests_user_id_pending_unique 
                ON teacher_requests (user_id) 
                WHERE status = \'pending\''
            );
        } else {
            // MySQL no soporta índices parciales
            // Se confía en el lockForUpdate() en la transacción de TeacherRequestService
            // Agregamos un índice compuesto para mejorar el rendimiento de la consulta
            Schema::table('teacher_requests', function (Blueprint $table) {
                $table->index(['user_id', 'status'], 'teacher_requests_user_status_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS teacher_requests_user_id_pending_unique');
        } else {
            Schema::table('teacher_requests', function (Blueprint $table) {
                $table->dropIndex('teacher_requests_user_status_index');
            });
        }
    }
};
