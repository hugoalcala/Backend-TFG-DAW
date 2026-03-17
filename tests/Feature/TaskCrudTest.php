<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCrudTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // GET /api/tasks
    // ---------------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/tasks')->assertUnauthorized();
    }

    public function test_index_returns_only_authenticated_user_tasks(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Task::create(['user_id' => $user->id,  'title' => 'Mi tarea',  'status' => 'pending',  'category' => 'general']);
        Task::create(['user_id' => $other->id, 'title' => 'Ajena',     'status' => 'pending',  'category' => 'general']);

        $this->actingAs($user)
            ->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Mi tarea');
    }

    public function test_index_filters_by_status(): void
    {
        $user = User::factory()->create();

        Task::create(['user_id' => $user->id, 'title' => 'Pendiente',  'status' => 'pending',     'category' => 'general']);
        Task::create(['user_id' => $user->id, 'title' => 'Completada', 'status' => 'completed',   'category' => 'general']);

        $this->actingAs($user)
            ->getJson('/api/tasks?status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Completada');
    }

    public function test_index_filters_by_category(): void
    {
        $user = User::factory()->create();

        Task::create(['user_id' => $user->id, 'title' => 'Trabajo',  'status' => 'pending', 'category' => 'trabajo']);
        Task::create(['user_id' => $user->id, 'title' => 'Personal', 'status' => 'pending', 'category' => 'personal']);

        $this->actingAs($user)
            ->getJson('/api/tasks?category=trabajo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Trabajo');
    }

    public function test_index_returns_paginator_structure(): void
    {
        $user = User::factory()->create();
        Task::create(['user_id' => $user->id, 'title' => 'T1', 'status' => 'pending', 'category' => 'general']);

        $this->actingAs($user)
            ->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonStructure(['current_page', 'data', 'total', 'per_page', 'last_page']);
    }

    // ---------------------------------------------------------------
    // POST /api/tasks
    // ---------------------------------------------------------------

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/tasks', ['title' => 'Test'])->assertUnauthorized();
    }

    public function test_store_creates_task_with_defaults(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/tasks', ['title' => 'Nueva tarea'])
            ->assertCreated()
            ->assertJsonPath('title', 'Nueva tarea')
            ->assertJsonPath('category', 'general')
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('user_id', $user->id);

        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Nueva tarea', 'category' => 'general']);
    }

    public function test_store_validates_title_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/tasks', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_validates_category_enum(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/tasks', ['title' => 'Test', 'category' => 'invalida'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    // ---------------------------------------------------------------
    // GET /api/tasks/{task}
    // ---------------------------------------------------------------

    public function test_show_returns_task_for_owner(): void
    {
        $user = User::factory()->create();
        $task = Task::create(['user_id' => $user->id, 'title' => 'Ver tarea', 'status' => 'pending', 'category' => 'general']);

        $this->actingAs($user)
            ->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('id', $task->id);
    }

    public function test_show_returns_403_for_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $task  = Task::create(['user_id' => $owner->id, 'title' => 'Privada', 'status' => 'pending', 'category' => 'general']);

        $this->actingAs($other)
            ->getJson("/api/tasks/{$task->id}")
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // PUT /api/tasks/{task}
    // ---------------------------------------------------------------

    public function test_update_returns_403_for_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $task  = Task::create(['user_id' => $owner->id, 'title' => 'Original', 'status' => 'pending', 'category' => 'general']);

        $this->actingAs($other)
            ->putJson("/api/tasks/{$task->id}", ['title' => 'Hackeo'])
            ->assertForbidden();
    }

    public function test_update_sets_completed_at_when_status_becomes_completed(): void
    {
        $user = User::factory()->create();
        $task = Task::create(['user_id' => $user->id, 'title' => 'Completar', 'status' => 'pending', 'category' => 'general']);

        $this->actingAs($user)
            ->putJson("/api/tasks/{$task->id}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('status', 'completed');

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_update_clears_completed_at_when_status_reverts(): void
    {
        $user = User::factory()->create();
        $task = Task::create([
            'user_id'      => $user->id,
            'title'        => 'Revertir',
            'status'       => 'completed',
            'category'     => 'general',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->putJson("/api/tasks/{$task->id}", ['status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_update_changes_title_and_category(): void
    {
        $user = User::factory()->create();
        $task = Task::create(['user_id' => $user->id, 'title' => 'Viejo', 'status' => 'pending', 'category' => 'general']);

        $this->actingAs($user)
            ->putJson("/api/tasks/{$task->id}", ['title' => 'Nuevo', 'category' => 'estudio'])
            ->assertOk()
            ->assertJsonPath('title', 'Nuevo')
            ->assertJsonPath('category', 'estudio');
    }

    // ---------------------------------------------------------------
    // DELETE /api/tasks/{task}
    // ---------------------------------------------------------------

    public function test_destroy_deletes_task_for_owner(): void
    {
        $user = User::factory()->create();
        $task = Task::create(['user_id' => $user->id, 'title' => 'Borrar', 'status' => 'pending', 'category' => 'general']);

        $this->actingAs($user)
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Tarea eliminada correctamente');

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_destroy_returns_403_for_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $task  = Task::create(['user_id' => $owner->id, 'title' => 'Ajena', 'status' => 'pending', 'category' => 'general']);

        $this->actingAs($other)
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }
}
