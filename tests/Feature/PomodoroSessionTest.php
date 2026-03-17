<?php

namespace Tests\Feature;

use App\Models\PomodoroSession;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PomodoroSessionTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // GET /api/pomodoro-sessions
    // ---------------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/pomodoro-sessions')->assertUnauthorized();
    }

    public function test_index_returns_only_authenticated_user_sessions(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        PomodoroSession::create([
            'user_id'          => $user->id,
            'duration_minutes' => 25,
            'type'             => 'focus',
            'started_at'       => now()->subHour(),
            'completed'        => true,
        ]);

        PomodoroSession::create([
            'user_id'          => $other->id,
            'duration_minutes' => 25,
            'type'             => 'focus',
            'started_at'       => now()->subHour(),
            'completed'        => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/pomodoro-sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_includes_task_relation(): void
    {
        $user = User::factory()->create();
        $task = Task::create(['user_id' => $user->id, 'title' => 'Estudiar', 'status' => 'pending', 'category' => 'estudio']);

        PomodoroSession::create([
            'user_id'          => $user->id,
            'task_id'          => $task->id,
            'duration_minutes' => 25,
            'type'             => 'focus',
            'started_at'       => now()->subHour(),
            'completed'        => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/pomodoro-sessions')
            ->assertOk()
            ->assertJsonPath('data.0.task.title', 'Estudiar')
            ->assertJsonStructure(['current_page', 'data', 'total', 'per_page', 'last_page']);
    }

    // ---------------------------------------------------------------
    // POST /api/pomodoro-sessions
    // ---------------------------------------------------------------

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/pomodoro-sessions', ['started_at' => now()->toISOString()])
            ->assertUnauthorized();
    }

    public function test_store_creates_session_without_task(): void
    {
        $user = User::factory()->create();

        $started = now()->subMinutes(25)->toISOString();
        $ended   = now()->toISOString();

        $this->actingAs($user)
            ->postJson('/api/pomodoro-sessions', [
                'duration_minutes' => 25,
                'type'             => 'focus',
                'started_at'       => $started,
                'ended_at'         => $ended,
                'completed'        => true,
            ])
            ->assertCreated()
            ->assertJsonPath('duration_minutes', 25)
            ->assertJsonPath('type', 'focus')
            ->assertJsonPath('completed', true)
            ->assertJsonPath('user_id', $user->id);

        $this->assertDatabaseHas('pomodoro_sessions', ['user_id' => $user->id, 'duration_minutes' => 25]);
    }

    public function test_store_creates_session_with_own_task(): void
    {
        $user = User::factory()->create();
        $task = Task::create(['user_id' => $user->id, 'title' => 'Repasar PHP', 'status' => 'pending', 'category' => 'estudio']);

        $this->actingAs($user)
            ->postJson('/api/pomodoro-sessions', [
                'task_id'          => $task->id,
                'duration_minutes' => 25,
                'started_at'       => now()->subMinutes(25)->toISOString(),
                'completed'        => false,
            ])
            ->assertCreated()
            ->assertJsonPath('task_id', $task->id)
            ->assertJsonPath('task.title', 'Repasar PHP');
    }

    public function test_store_rejects_task_belonging_to_another_user(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $task  = Task::create(['user_id' => $other->id, 'title' => 'Ajena', 'status' => 'pending', 'category' => 'general']);

        $this->actingAs($user)
            ->postJson('/api/pomodoro-sessions', [
                'task_id'    => $task->id,
                'started_at' => now()->toISOString(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Tarea no válida');
    }

    public function test_store_validates_started_at_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/pomodoro-sessions', ['duration_minutes' => 25])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['started_at']);
    }

    public function test_store_applies_default_duration_and_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/pomodoro-sessions', [
                'started_at' => now()->toISOString(),
            ])
            ->assertCreated()
            ->assertJsonPath('duration_minutes', 25)
            ->assertJsonPath('type', 'focus');
    }
}
