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
        Schema::table('messages', function (Blueprint $table) {
            // Agregar conversation_id
            $table->unsignedBigInteger('conversation_id')->nullable()->after('id');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            
            // Cambiar los campos de subject a message para ser más consistente
            $table->text('message')->nullable()->after('conversation_id');
            
            // Agregar índice
            $table->index('conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn('conversation_id');
            $table->dropColumn('message');
        });
    }
};
