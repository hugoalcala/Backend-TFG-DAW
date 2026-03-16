<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeachersListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_approved_teachers_for_students(): void
    {
        $approvedTeacher = User::factory()->create([
            'name' => 'Ana Profe',
            'role' => 'teacher',
            'teacher_status' => 'approved',
            'subject' => 'Matematicas',
            'bio' => 'Profesora particular de secundaria',
            'price_per_hour' => 20,
        ]);

        User::factory()->create([
            'name' => 'Profesor Pendiente',
            'role' => 'teacher',
            'teacher_status' => 'pending',
        ]);

        User::factory()->create([
            'name' => 'Alumno Normal',
            'role' => 'user',
            'teacher_status' => null,
        ]);

        $response = $this->getJson('/api/teachers');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'teachers')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('teachers.0.id', $approvedTeacher->id)
            ->assertJsonPath('teachers.0.name', 'Ana Profe')
            ->assertJsonPath('teachers.0.subject', 'Matematicas')
            ->assertJsonPath('teachers.0.teacher_status', 'approved')
            ->assertJsonPath('teachers.0.role', 'teacher');
    }

    public function test_it_returns_empty_list_when_there_are_no_approved_teachers(): void
    {
        User::factory()->create([
            'role' => 'teacher',
            'teacher_status' => 'pending',
        ]);

        $response = $this->getJson('/api/teachers');

        $response
            ->assertOk()
            ->assertJsonPath('teachers', [])
            ->assertJsonPath('data', []);
    }
}