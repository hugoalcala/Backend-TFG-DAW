<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

try {
    // Verificar si el usuario ya existe
    $existing = User::where('email', 'test@gmail.com')->first();
    if ($existing) {
        echo "Usuario ya existe:\n";
        echo json_encode($existing->toArray(), JSON_PRETTY_PRINT);
        exit;
    }

    // Crear usuario de prueba
    $user = User::create([
        'name' => 'Test User Google',
        'email' => 'test@gmail.com',
        'password' => bcrypt('password123'),
        'google_id' => '123456789',
    ]);

    echo "✅ Usuario creado exitosamente:\n";
    echo json_encode($user->toArray(), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
