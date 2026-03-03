<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$users = User::all(['id', 'name', 'email', 'google_id', 'created_at']);

echo "\n=== 📊 Usuarios en la Base de Datos ===\n\n";
echo str_pad("ID", 5) . str_pad("Nombre", 25) . str_pad("Email", 30) . str_pad("Google ID", 12) . "\n";
echo str_repeat("-", 75) . "\n";

foreach ($users as $user) {
    $googleId = $user->google_id ? "Sí" : "No";
    echo str_pad($user->id, 5) . 
         str_pad(substr($user->name, 0, 23), 25) . 
         str_pad(substr($user->email, 0, 28), 30) . 
         str_pad($googleId, 12) . "\n";
}

echo "\n✅ Total: " . $users->count() . " usuario(s)\n\n";
