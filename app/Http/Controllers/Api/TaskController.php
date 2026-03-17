<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Task::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc');

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('category')) {
                $query->where('category', $request->input('category'));
            }

            $perPage = max(1, min(100, $request->integer('per_page', 20)));

            return response()->json($query->paginate($perPage)->appends($request->query()));
        } catch (Throwable $e) {
            Log::error('Unable to fetch tasks', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['error' => 'Unable to fetch tasks'], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category'    => 'sometimes|in:general,trabajo,personal,estudio,salud',
            'due_date'    => 'nullable|date',
            'started_at'  => 'nullable|date',
        ]);

        $task = Task::create([
            'user_id'     => $request->user()->id,
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category'    => $validated['category'] ?? 'general',
            'status'      => 'pending',
            'due_date'    => $validated['due_date'] ?? null,
            'started_at'  => $validated['started_at'] ?? null,
        ]);

        return response()->json($task, 201);
    }

    public function show(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json($task);
    }

    public function update(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category'    => 'sometimes|in:general,trabajo,personal,estudio,salud',
            'status'      => 'sometimes|in:pending,in_progress,completed',
            'due_date'    => 'nullable|date',
            'started_at'  => 'nullable|date',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'completed' && $task->status !== 'completed') {
            $task->completed_at = now();
        } elseif (isset($validated['status']) && $validated['status'] !== 'completed') {
            $task->completed_at = null;
        }

        $task->fill($validated)->save();

        return response()->json($task->fresh());
    }

    public function destroy(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $task->delete();

        return response()->json(['message' => 'Tarea eliminada correctamente']);
    }
}
