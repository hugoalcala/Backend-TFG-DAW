<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PomodoroSession;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PomodoroSessionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = max(1, min(100, $request->integer('per_page', 20)));

            $sessions = PomodoroSession::where('user_id', $request->user()->id)
                ->with('task:id,title')
                ->orderBy('started_at', 'desc')
                ->paginate($perPage)
                ->appends($request->query());

            return response()->json($sessions);
        } catch (Throwable $e) {
            Log::error('Unable to fetch pomodoro sessions', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['error' => 'Unable to fetch pomodoro sessions'], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'task_id'          => 'nullable|integer|exists:tasks,id',
            'duration_minutes' => 'sometimes|integer|min:1|max:120',
            'type'             => 'sometimes|in:focus,short_break,long_break',
            'started_at'       => 'required|date',
            'ended_at'         => 'nullable|date|after_or_equal:started_at',
            'completed'        => 'sometimes|boolean',
        ]);

        // Make sure the task belongs to the authenticated user
        if (!empty($validated['task_id'])) {
            $task = Task::find($validated['task_id']);
            if (!$task || $task->user_id !== $request->user()->id) {
                return response()->json(['error' => 'Tarea no válida'], 422);
            }
        }

        $session = PomodoroSession::create([
            'user_id'          => $request->user()->id,
            'task_id'          => $validated['task_id'] ?? null,
            'duration_minutes' => $validated['duration_minutes'] ?? 25,
            'type'             => $validated['type'] ?? 'focus',
            'started_at'       => $validated['started_at'],
            'ended_at'         => $validated['ended_at'] ?? null,
            'completed'        => $validated['completed'] ?? false,
        ]);

        return response()->json($session->load('task:id,title'), 201);
    }
}
