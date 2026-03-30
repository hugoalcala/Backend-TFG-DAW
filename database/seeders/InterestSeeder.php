<?php

namespace Database\Seeders;

use App\Models\Interest;
use Illuminate\Database\Seeder;

class InterestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $interests = [
            ['name' => 'Programación', 'description' => 'Desarrollo de software y aplicaciones'],
            ['name' => 'Diseño Gráfico', 'description' => 'Diseño visual y creatividad'],
            ['name' => 'Marketing Digital', 'description' => 'Estrategias de marketing en línea'],
            ['name' => 'Escribir', 'description' => 'Redacción y contenido'],
            ['name' => 'Idiomas', 'description' => 'Aprendizaje de nuevos idiomas'],
            ['name' => 'Música', 'description' => 'Enseñanza y aprendizaje de música'],
            ['name' => 'Matemáticas', 'description' => 'Tutorías de matemáticas'],
            ['name' => 'Ciencias', 'description' => 'Biología, Física, Química'],
            ['name' => 'Historia', 'description' => 'Enseñanza de historia'],
            ['name' => 'Arte', 'description' => 'Artes visuales y expresión creativa'],
            ['name' => 'Fitness', 'description' => 'Entrenamiento y ejercicio físico'],
            ['name' => 'Cocina', 'description' => 'Gastronomía y técnicas culinarias'],
            ['name' => 'Fotografía', 'description' => 'Técnicas y arte de la fotografía'],
            ['name' => 'Negocio', 'description' => 'Asesoramiento empresarial'],
            ['name' => 'Desarrollo Personal', 'description' => 'Crecimiento y superación personal'],
        ];

        foreach ($interests as $interest) {
            Interest::firstOrCreate(
                ['name' => $interest['name']],
                ['description' => $interest['description']]
            );
        }
    }
}
