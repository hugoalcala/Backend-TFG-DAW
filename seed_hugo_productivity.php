<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PomodoroSession;
use App\Models\Task;
use App\Models\User;

$email = 'hugo.pascual845@gmail.com';
$user = User::where('email', $email)->first();

if (!$user) {
    echo "ERROR: Usuario no encontrado para email {$email}\n";
    exit(1);
}

$now = now();

// Create or reuse demo tasks for this user.
$tasksData = [
    [
        'title' => 'Repasar Laravel routes',
        'description' => 'Comprobar compatibilidad de endpoints del panel de productividad',
        'category' => 'estudio',
        'status' => 'completed',
        'completed_at' => $now->copy()->subDays(1)->setHour(19)->setMinute(30),
    ],
    [
        'title' => 'Preparar ejercicios de algebra',
        'description' => 'Material para clase particular',
        'category' => 'trabajo',
        'status' => 'in_progress',
        'completed_at' => null,
    ],
    [
        'title' => 'Rutina de lectura',
        'description' => '20 paginas de libro tecnico',
        'category' => 'personal',
        'status' => 'pending',
        'completed_at' => null,
    ],
    [
        'title' => 'Entreno semanal',
        'description' => 'Sesion de movilidad y cardio',
        'category' => 'salud',
        'status' => 'completed',
        'completed_at' => $now->copy()->subDays(3)->setHour(20)->setMinute(10),
    ],
    [
        'title' => 'Revisar feedback alumnos',
        'description' => 'Leer comentarios de la ultima semana',
        'category' => 'trabajo',
        'status' => 'completed',
        'completed_at' => $now->copy()->startOfDay()->setHour(12)->setMinute(0),
    ],
];

$tasksByTitle = [];
foreach ($tasksData as $taskData) {
    $task = Task::firstOrCreate(
        [
            'user_id' => $user->id,
            'title' => $taskData['title'],
        ],
        [
            'description' => $taskData['description'],
            'category' => $taskData['category'],
            'status' => $taskData['status'],
            'completed_at' => $taskData['completed_at'],
        ]
    );

    // Keep existing tasks aligned with expected status for meaningful metrics.
    $task->description = $taskData['description'];
    $task->category = $taskData['category'];
    $task->status = $taskData['status'];
    $task->completed_at = $taskData['completed_at'];
    $task->save();

    $tasksByTitle[$task->title] = $task;
}

$sessionsData = [
    [
        'task_title' => 'Repasar Laravel routes',
        'duration_minutes' => 25,
        'type' => 'focus',
        'started_at' => $now->copy()->subDays(1)->setHour(18)->setMinute(0),
        'ended_at' => $now->copy()->subDays(1)->setHour(18)->setMinute(25),
        'completed' => true,
    ],
    [
        'task_title' => 'Preparar ejercicios de algebra',
        'duration_minutes' => 50,
        'type' => 'focus',
        'started_at' => $now->copy()->startOfDay()->setHour(9)->setMinute(0),
        'ended_at' => $now->copy()->startOfDay()->setHour(9)->setMinute(50),
        'completed' => true,
    ],
    [
        'task_title' => 'Preparar ejercicios de algebra',
        'duration_minutes' => 5,
        'type' => 'short_break',
        'started_at' => $now->copy()->startOfDay()->setHour(9)->setMinute(50),
        'ended_at' => $now->copy()->startOfDay()->setHour(9)->setMinute(55),
        'completed' => true,
    ],
    [
        'task_title' => 'Revisar feedback alumnos',
        'duration_minutes' => 25,
        'type' => 'focus',
        'started_at' => $now->copy()->startOfDay()->setHour(11)->setMinute(0),
        'ended_at' => $now->copy()->startOfDay()->setHour(11)->setMinute(25),
        'completed' => true,
    ],
    [
        'task_title' => 'Rutina de lectura',
        'duration_minutes' => 25,
        'type' => 'focus',
        'started_at' => $now->copy()->subDays(2)->setHour(21)->setMinute(0),
        'ended_at' => $now->copy()->subDays(2)->setHour(21)->setMinute(25),
        'completed' => false,
    ],
];

$createdSessions = 0;
foreach ($sessionsData as $sessionData) {
    $task = $tasksByTitle[$sessionData['task_title']] ?? null;

    $exists = PomodoroSession::where('user_id', $user->id)
        ->where('task_id', optional($task)->id)
        ->where('type', $sessionData['type'])
        ->where('started_at', $sessionData['started_at'])
        ->exists();

    if ($exists) {
        continue;
    }

    PomodoroSession::create([
        'user_id' => $user->id,
        'task_id' => optional($task)->id,
        'duration_minutes' => $sessionData['duration_minutes'],
        'type' => $sessionData['type'],
        'started_at' => $sessionData['started_at'],
        'ended_at' => $sessionData['ended_at'],
        'completed' => $sessionData['completed'],
    ]);

    $createdSessions++;
}

$taskCount = Task::where('user_id', $user->id)->count();
$completedTaskCount = Task::where('user_id', $user->id)->where('status', 'completed')->count();
$sessionCount = PomodoroSession::where('user_id', $user->id)->count();
$focusMinutesWeek = PomodoroSession::where('user_id', $user->id)
    ->where('type', 'focus')
    ->where('completed', true)
    ->where('started_at', '>=', now()->startOfWeek())
    ->sum('duration_minutes');

echo "OK|user_id={$user->id}|tasks={$taskCount}|completed_tasks={$completedTaskCount}|sessions={$sessionCount}|new_sessions={$createdSessions}|focus_minutes_week={$focusMinutesWeek}\n";
