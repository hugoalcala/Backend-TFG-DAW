<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;

// Rutas de autenticación pública
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas de autenticación con Google
Route::get('/auth/google', [AuthController::class, 'googleRedirect']);
Route::post('/auth/google/callback', [AuthController::class, 'googleCallback']);

// Rutas protegidas (requieren autenticación)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', function (Request $request) {
        return $request->user();
    });
});

// Rutas de administración (requieren autenticación y rol de admin)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Dashboard - estadísticas generales
    Route::get('/stats', [AdminController::class, 'getStats']);
    
    // Gestión de profesores pendientes
    Route::get('/teachers/pending', [AdminController::class, 'getPendingTeachers']);
    Route::post('/teachers/{id}/approve', [AdminController::class, 'approveTeacher']);
    Route::post('/teachers/{id}/reject', [AdminController::class, 'rejectTeacher']);
    
    // Gestión de usuarios
    Route::get('/users', [AdminController::class, 'getUsers']);
    Route::put('/users/{id}', [AdminController::class, 'updateUser']);
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
    
    // Gestión de publicaciones
    Route::get('/posts', [AdminController::class, 'getPosts']);
    Route::delete('/posts/{id}', [AdminController::class, 'deletePost']);
});
