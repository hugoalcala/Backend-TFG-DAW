<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PomodoroSession;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductivityMetricsController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->input('period', 'week');

        $from = match ($period) {
            'today' => now()->startOfDay(),
            'month' => now()->startOfMonth(),
            default => now()->startOfWeek(),
        };

        try {
            $userId = $request->user()->id;

            $totalTasks      = Task::where('user_id', $userId)->count();
            $completedTasks  = Task::where('user_id', $userId)->where('status', 'completed')->count();
            $periodCompleted = Task::where('user_id', $userId)
                ->where('status', 'completed')
                ->where('completed_at', '>=', $from)
                ->count();

            $sessions = PomodoroSession::where('user_id', $userId)
                ->where('type', 'focus')
                ->where('started_at', '>=', $from)
                ->get(['started_at', 'duration_minutes']);

            $totalSessions  = $sessions->count();
            $focusMinutes   = $sessions->sum('duration_minutes');

            // Build daily breakdown for the period (1, 7, or 30 days up to today)
            $days = match ($period) {
                'today' => 1,
                'month' => 30,
                default => 7,
            };
            $daily = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $day  = now()->subDays($i)->toDateString();
                $from = now()->subDays($i)->startOfDay();
                $to   = now()->subDays($i)->endOfDay();

                $daily[] = [
                    'date' => $day,
                    'completed_tasks' => Task::where('user_id', $userId)
                        ->where('status', 'completed')
                        ->whereBetween('completed_at', [$from, $to])
                        ->count(),
                    'focus_sessions' => PomodoroSession::where('user_id', $userId)
                        ->where('type', 'focus')
                        ->whereBetween('started_at', [$from, $to])
                        ->count(),
                ];
            }

            return response()->json([
                'period'            => $period,
                'total_tasks'       => $totalTasks,
                'completed_tasks'   => $completedTasks,
                'period_completed'  => $periodCompleted,
                'completion_rate'   => $totalTasks > 0 ? round($completedTasks / $totalTasks * 100, 1) : 0,
                'focus_sessions'    => $totalSessions,
                'focus_minutes'     => $focusMinutes,
                'focus_hours'       => round($focusMinutes / 60, 1),
                'daily_breakdown'   => $daily,
            ]);
        } catch (Throwable $e) {
            Log::error('Unable to fetch productivity metrics', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['error' => 'Unable to fetch productivity metrics'], 500);
        }
    }
}
