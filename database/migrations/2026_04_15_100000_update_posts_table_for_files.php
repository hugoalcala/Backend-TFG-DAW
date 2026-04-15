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
        Schema::table('posts', function (Blueprint $table) {
            // Renombrar image_path a file_path y agregar file_type
            $table->renameColumn('image_path', 'file_path');
            $table->string('file_type')->nullable()->comment('Tipo de archivo: image, video, document, etc');
            $table->string('file_name')->nullable()->comment('Nombre original del archivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->renameColumn('file_path', 'image_path');
            $table->dropColumn(['file_type', 'file_name']);
        });
    }
};
