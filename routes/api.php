<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\TeacherRequestController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\PomodoroSessionController;
use App\Http\Controllers\Api\ProductivityMetricsController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RatingController;

// Rutas de autenticación pública
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/teachers', [TeacherController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('teachers.index');

// Rutas públicas de reseñas de profesores
Route::get('/teachers/{teacherId}/ratings', [RatingController::class, 'getTeacherRatings'])
    ->middleware('throttle:60,1')
    ->name('ratings.get');

// Rutas de recuperación de contraseña
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Rutas públicas de intereses
Route::get('/interests', [ProfileController::class, 'listAllInterests']);

// Rutas de autenticación con Google
Route::get('/auth/google', [AuthController::class, 'googleRedirect']);
Route::post('/auth/google/callback', [AuthController::class, 'googleCallback']);

// Rutas protegidas (requieren autenticación)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/me', [AuthController::class, 'updateProfile']);
    Route::get('/profile', [AuthController::class, 'me']);
    Route::match(['post', 'put', 'patch'], '/profile', [AuthController::class, 'updateProfile']);
    Route::match(['post', 'put', 'patch'], '/profile/avatar', [AuthController::class, 'updateAvatar']);
    Route::delete('/profile/avatar', [AuthController::class, 'deleteAvatar']);

    // Rutas de intereses
    Route::get('/profile/interests', [ProfileController::class, 'getInterests']);
    Route::match(['post', 'put', 'patch'], '/profile/interests', [ProfileController::class, 'updateInterests']);

    // Rutas de solicitud de profesor
    Route::post('/become-teacher', [TeacherRequestController::class, 'store']);
    Route::get('/teacher-request/status', [TeacherRequestController::class, 'status']);
    Route::delete('/teacher-request/cancel', [TeacherRequestController::class, 'cancel']);

    // Productividad — tareas
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // Productividad — sesiones Pomodoro
    Route::get('/pomodoro-sessions', [PomodoroSessionController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('pomodoro.index');
    Route::post('/pomodoro-sessions', [PomodoroSessionController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('pomodoro.store');

    // Productividad — métricas
    Route::get('/productivity-metrics', [ProductivityMetricsController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('productivity.metrics');

    // Rutas de reseñas (protegidas)
    Route::post('/teachers/{teacherId}/ratings', [RatingController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('ratings.store');
    Route::put('/teachers/{teacherId}/ratings/{ratingId}', [RatingController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('ratings.update');
    Route::delete('/teachers/{teacherId}/ratings/{ratingId}', [RatingController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('ratings.destroy');
});

// Rutas de administración (requieren autenticación y rol de admin)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Dashboard - estadísticas generales
    Route::get('/stats', [AdminController::class, 'getStats']);
    
    // Gestión de profesores pendientes
    Route::get('/pending-teachers', [AdminController::class, 'getPendingTeachers']);
    Route::post('/approve-teacher/{id}', [AdminController::class, 'approveTeacher']);
    Route::post('/reject-teacher/{id}', [AdminController::class, 'rejectTeacher']);
    Route::get('/teacher-request/{id}/certificate', [AdminController::class, 'downloadCertificate']);
    
    // Gestión de usuarios
    Route::get('/users', [AdminController::class, 'getUsers']);
    Route::put('/users/{id}', [AdminController::class, 'updateUser']);
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
    
    // Gestión de publicaciones
    Route::get('/posts', [AdminController::class, 'getPosts']);
    Route::delete('/posts/{id}', [AdminController::class, 'deletePost']);
});
