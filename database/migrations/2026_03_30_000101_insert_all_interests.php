<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Interest;

return new class extends Migration
{
    public function up(): void
    {
        $interests = [
            'Programación',
            'Matemáticas',
            'Inglés',
            'Historia',
            'Ciencias',
            'Diseño',
            'Física',
            'Química',
            'Literatura',
            'Arte',
            'Música',
            'Deportes',
            'Negocios',
            'Marketing',
            'Desarrollo Web',
            'Base de Datos',
        ];

        foreach ($interests as $interestName) {
            Interest::firstOrCreate(
                ['name' => $interestName],
                ['description' => "$interestName - Área de interés"]
            );
        }
    }

    public function down(): void
    {
        // No eliminar intereses existentes
    }
};
