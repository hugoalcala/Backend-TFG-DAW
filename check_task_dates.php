<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Task;

// Get an existing task from hugo.pascual845 (user_id=5) and set a due_date
$task = Task::where('user_id', 5)->first();
if (!$task) {
    echo "NO_TASK_FOUND\n";
    exit(1);
}

$task->due_date  = '2026-03-20';
$task->started_at = '2026-03-16 10:00:00';
$task->save();

$fresh = Task::find($task->id);
echo "task_id={$fresh->id}\n";
echo "due_date={$fresh->due_date}\n";
echo "started_at={$fresh->started_at}\n";
echo "completed_at={$fresh->completed_at}\n";
