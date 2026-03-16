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
            'email' => 'ana@example.com',
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
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approvedTeacher->id)
            ->assertJsonPath('data.0.name', 'Ana Profe')
            ->assertJsonPath('data.0.subject', 'Matematicas')
            ->assertJsonPath('data.0.teacher_status', 'approved')
            ->assertJsonPath('data.0.role', 'teacher')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonStructure([
                'current_page',
                'data' => [[
                    'id',
                    'name',
                    'subject',
                    'bio',
                    'price_per_hour',
                    'avatar_path',
                    'avatar_url',
                    'role',
                    'teacher_status',
                    'created_at',
                    'updated_at',
                ]],
                'first_page_url',
                'from',
                'last_page',
                'last_page_url',
                'links',
                'next_page_url',
                'path',
                'per_page',
                'prev_page_url',
                'to',
                'total',
            ]);
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
            ->assertJsonPath('data', [])
            ->assertJsonPath('total', 0)
            ->assertJsonPath('per_page', 12);
    }
}