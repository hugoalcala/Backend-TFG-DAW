<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar si ya existe un admin
        $existingAdmin = User::where('email', 'admin@educonnect.com')->first();
        
        if (!$existingAdmin) {
            User::create([
                'name' => 'Admin User',
                'email' => 'admin@educonnect.com',
                'password' => Hash::make('admin123456'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);
            
            $this->command->info('Usuario administrador creado exitosamente.');
            $this->command->info('Email: admin@educonnect.com');
            $this->command->info('Password: admin123456');
        } else {
            $this->command->warn('El usuario administrador ya existe.');
        }
    }
}
