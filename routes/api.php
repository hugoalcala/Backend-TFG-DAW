<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\TeacherRequestController;

// Rutas de autenticación pública
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas de recuperación de contraseña
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Rutas de autenticación con Google
Route::get('/auth/google', [AuthController::class, 'googleRedirect']);
Route::post('/auth/google/callback', [AuthController::class, 'googleCallback']);

// Rutas protegidas (requieren autenticación)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', function (Request $request) {
        return $request->user();
    });

    // Rutas de solicitud de profesor
    Route::post('/become-teacher', [TeacherRequestController::class, 'store']);
    Route::get('/teacher-request/status', [TeacherRequestController::class, 'status']);
    Route::delete('/teacher-request/cancel', [TeacherRequestController::class, 'cancel']);
});

// Rutas de administración (requieren autenticación y rol de admin)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Dashboard - estadísticas generales
    Route::get('/stats', [AdminController::class, 'getStats']);
    
    // Gestión de profesores pendientes
    Route::get('/pending-teachers', [AdminController::class, 'getPendingTeachers']);
    Route::post('/approve-teacher/{id}', [AdminController::class, 'approveTeacher']);
    Route::post('/reject-teacher/{id}', [AdminController::class, 'rejectTeacher']);
    
    // Gestión de usuarios
    Route::get('/users', [AdminController::class, 'getUsers']);
    Route::put('/users/{id}', [AdminController::class, 'updateUser']);
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
    
    // Gestión de publicaciones
    Route::get('/posts', [AdminController::class, 'getPosts']);
    Route::delete('/posts/{id}', [AdminController::class, 'deletePost']);
});
