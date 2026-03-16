<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PomodoroSession;
use App\Models\Task;
use App\Models\User;

$user = User::where('email', 'hugo.pascual845@gmail.com')->first();

if (!$user) {
    echo "ERROR:USER_NOT_FOUND\n";
    exit(1);
}

$tasks = Task::where('user_id', $user->id)->orderBy('id')->get(['id', 'title', 'status', 'category', 'completed_at']);
$sessions = PomodoroSession::where('user_id', $user->id)->orderBy('started_at')->get(['id', 'task_id', 'duration_minutes', 'type', 'started_at', 'completed']);

echo "USER|{$user->id}|{$user->email}\n";
echo "TASK_COUNT|" . $tasks->count() . "\n";
echo "SESSION_COUNT|" . $sessions->count() . "\n";

foreach ($tasks as $task) {
    echo "TASK|{$task->id}|{$task->title}|{$task->status}|{$task->category}|" . ($task->completed_at ? $task->completed_at->toDateTimeString() : 'null') . "\n";
}

foreach ($sessions as $session) {
    echo "SESSION|{$session->id}|{$session->task_id}|{$session->duration_minutes}|{$session->type}|{$session->started_at}|" . ($session->completed ? 'true' : 'false') . "\n";
}
