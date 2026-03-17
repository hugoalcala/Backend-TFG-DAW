<?php

namespace Tests\Feature;

use App\Models\PomodoroSession;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductivityMetricsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // GET /api/productivity-metrics
    // ---------------------------------------------------------------

    public function test_metrics_requires_authentication(): void
    {
        $this->getJson('/api/productivity-metrics')->assertUnauthorized();
    }

    public function test_metrics_returns_correct_structure(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/productivity-metrics')
            ->assertOk()
            ->assertJsonStructure([
                'period',
                'total_tasks',
                'completed_tasks',
                'period_completed',
                'completion_rate',
                'focus_sessions',
                'focus_minutes',
                'focus_hours',
                'daily_breakdown' => [['date', 'completed_tasks', 'focus_sessions']],
            ]);
    }

    public function test_metrics_defaults_to_week_period_with_7_day_breakdown(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/productivity-metrics')
            ->assertOk()
            ->assertJsonPath('period', 'week');

        $this->assertCount(7, $response->json('daily_breakdown'));
    }

    public function test_metrics_today_period_returns_1_day_breakdown(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/productivity-metrics?period=today')
            ->assertOk()
            ->assertJsonPath('period', 'today');

        $this->assertCount(1, $response->json('daily_breakdown'));
    }

    public function test_metrics_month_period_returns_30_day_breakdown(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/productivity-metrics?period=month')
            ->assertOk()
            ->assertJsonPath('period', 'month');

        $this->assertCount(30, $response->json('daily_breakdown'));
    }

    public function test_metrics_completion_rate_is_zero_with_no_tasks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/productivity-metrics')
            ->assertOk()
            ->assertJsonPath('total_tasks', 0)
            ->assertJsonPath('completed_tasks', 0)
            ->assertJsonPath('completion_rate', 0);
    }

    public function test_metrics_counts_total_and_completed_tasks(): void
    {
        $user = User::factory()->create();

        Task::create(['user_id' => $user->id, 'title' => 'Pendiente', 'status' => 'pending',   'category' => 'general']);
        Task::create(['user_id' => $user->id, 'title' => 'Hecha',     'status' => 'completed', 'category' => 'general', 'completed_at' => now()]);
        Task::create(['user_id' => $user->id, 'title' => 'Hecha 2',   'status' => 'completed', 'category' => 'general', 'completed_at' => now()]);

        $this->actingAs($user)
            ->getJson('/api/productivity-metrics')
            ->assertOk()
            ->assertJsonPath('total_tasks', 3)
            ->assertJsonPath('completed_tasks', 2)
            ->assertJsonPath('completion_rate', 66.7);
    }

    public function test_metrics_only_counts_authenticated_user_tasks(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        // Create tasks for other user — should not appear in metrics
        Task::create(['user_id' => $other->id, 'title' => 'Ajena',  'status' => 'completed', 'category' => 'general', 'completed_at' => now()]);
        Task::create(['user_id' => $other->id, 'title' => 'Ajena2', 'status' => 'pending',   'category' => 'general']);

        $this->actingAs($user)
            ->getJson('/api/productivity-metrics')
            ->assertOk()
            ->assertJsonPath('total_tasks', 0)
            ->assertJsonPath('completed_tasks', 0);
    }

    public function test_metrics_counts_focus_sessions_and_minutes(): void
    {
        $user = User::factory()->create();

        // Two completed focus sessions (25 min each)
        PomodoroSession::create([
            'user_id'          => $user->id,
            'duration_minutes' => 25,
            'type'             => 'focus',
            'started_at'       => now()->subMinutes(30),
            'completed'        => true,
        ]);

        PomodoroSession::create([
            'user_id'          => $user->id,
            'duration_minutes' => 25,
            'type'             => 'focus',
            'started_at'       => now()->subMinutes(60),
            'completed'        => true,
        ]);

        // A break session (should not be counted)
        PomodoroSession::create([
            'user_id'          => $user->id,
            'duration_minutes' => 5,
            'type'             => 'short_break',
            'started_at'       => now()->subMinutes(15),
            'completed'        => true,
        ]);

        // An incomplete focus session (should not be counted)
        PomodoroSession::create([
            'user_id'          => $user->id,
            'duration_minutes' => 25,
            'type'             => 'focus',
            'started_at'       => now()->subMinutes(10),
            'completed'        => false,
        ]);

        $this->actingAs($user)
            ->getJson('/api/productivity-metrics?period=week')
            ->assertOk()
            ->assertJsonPath('focus_sessions', 2)
            ->assertJsonPath('focus_minutes', 50)
            ->assertJsonPath('focus_hours', 0.8);
    }

    public function test_daily_breakdown_reflects_todays_activity(): void
    {
        $user = User::factory()->create();

        Task::create(['user_id' => $user->id, 'title' => 'Hoy', 'status' => 'completed', 'category' => 'general', 'completed_at' => now()]);

        PomodoroSession::create([
            'user_id'          => $user->id,
            'duration_minutes' => 25,
            'type'             => 'focus',
            'started_at'       => now()->subMinutes(30),
            'completed'        => true,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/productivity-metrics?period=today')
            ->assertOk();

        $breakdown = $response->json('daily_breakdown');
        $this->assertCount(1, $breakdown);
        $this->assertEquals(now()->toDateString(), $breakdown[0]['date']);
        $this->assertEquals(1, $breakdown[0]['completed_tasks']);
        $this->assertEquals(1, $breakdown[0]['focus_sessions']);
    }
}
