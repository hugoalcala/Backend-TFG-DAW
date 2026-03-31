<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Interest;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar intereses faltantes
        Interest::firstOrCreate(['name' => 'Desarrollo Web'], ['description' => 'Desarrollo de aplicaciones web']);
        Interest::firstOrCreate(['name' => 'Marketing'], ['description' => 'Estrategias de marketing']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No hacer nada, estos intereses pueden ser útiles
    }
};
