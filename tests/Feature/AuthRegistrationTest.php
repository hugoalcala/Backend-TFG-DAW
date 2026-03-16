<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_a_user_with_standard_payload(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Rosa Melano',
            'email' => 'rosa@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.name', 'Rosa Melano')
            ->assertJsonStructure(['message', 'user', 'token', 'role']);

        $this->assertDatabaseHas('users', [
            'email' => 'rosa@example.com',
            'name' => 'Rosa Melano',
        ]);
    }

    public function test_it_registers_a_user_with_spanish_name_fields(): void
    {
        $response = $this->postJson('/api/register', [
            'nombre' => 'Rosa',
            'apellido' => 'Melano',
            'email' => 'rosamelano@gmail.com',
            'password' => 'Password123',
            'confirmPassword' => 'Password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.name', 'Rosa Melano')
            ->assertJsonStructure(['message', 'user', 'token', 'role']);

        $user = User::where('email', 'rosamelano@gmail.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Rosa Melano', $user->name);
    }
}