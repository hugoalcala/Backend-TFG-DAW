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
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PaymentController;

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

// Ruta pública para test de claves de Stripe
Route::get('/payments/test-stripe-keys', [PaymentController::class, 'testStripeKeys'])
    ->name('payments.test-stripe-keys');

// Rutas protegidas (requieren autenticación)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/me', [AuthController::class, 'updateProfile']);
    Route::get('/profile', [AuthController::class, 'me']);
    Route::match(['post', 'put', 'patch'], '/profile', [AuthController::class, 'updateProfile']);
    Route::match(['post', 'put', 'patch'], '/profile/avatar', [AuthController::class, 'updateAvatar']);
    Route::delete('/profile/avatar', [AuthController::class, 'deleteAvatar']);

    // Obtener IDs de posts que el usuario ya le dio like
    Route::get('/user/liked-posts', [PostController::class, 'getUserLikedPosts']);

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
    
    // Rutas de reportes de reseñas
    Route::post('/teachers/{teacherId}/ratings/{ratingId}/report', [RatingController::class, 'reportRating'])
        ->middleware('throttle:30,1')
        ->name('ratings.report');

    // Rutas de posts/foro
    Route::get('/posts', [PostController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('posts.index');
    Route::post('/posts', [PostController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('posts.store');
    Route::match(['put', 'post'], '/posts/{id}', [PostController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('posts.update');
    Route::delete('/posts/{id}', [PostController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('posts.destroy');
    Route::post('/posts/{id}/like', [PostController::class, 'like'])
        ->middleware('throttle:60,1')
        ->name('posts.like');
    Route::post('/posts/{id}/unlike', [PostController::class, 'unlike'])
        ->middleware('throttle:60,1')
        ->name('posts.unlike');
    
    // Rutas de comentarios en posts
    Route::get('/posts/{id}/comments', [PostController::class, 'getComments'])
        ->middleware('throttle:60,1')
        ->name('posts.comments.index');
    Route::post('/posts/{id}/comments', [PostController::class, 'addComment'])
        ->middleware('throttle:30,1')
        ->name('posts.comments.store');
    Route::put('/posts/{postId}/comments/{commentId}', [PostController::class, 'updateComment'])
        ->middleware('throttle:30,1')
        ->name('posts.comments.update');
    Route::delete('/posts/{postId}/comments/{commentId}', [PostController::class, 'deleteComment'])
        ->middleware('throttle:30,1')
        ->name('posts.comments.destroy');
    
    // Rutas de mensajes/chat
    Route::get('/messages/conversations', [MessageController::class, 'getConversations'])
        ->middleware('throttle:60,1')
        ->name('messages.conversations.index');
    Route::post('/messages/conversations', [MessageController::class, 'createConversation'])
        ->middleware('throttle:30,1')
        ->name('messages.conversations.store');
    Route::get('/messages/conversations/{conversationId}', [MessageController::class, 'getConversation'])
        ->middleware('throttle:60,1')
        ->name('messages.conversations.show');
    Route::post('/messages/send', [MessageController::class, 'sendMessage'])
        ->middleware('throttle:60,1')
        ->name('messages.send');
    Route::put('/messages/conversations/{conversationId}/read', [MessageController::class, 'markAsRead'])
        ->middleware('throttle:30,1')
        ->name('messages.mark-read');
    
    // Rutas de admin para avisos/notificaciones
    Route::post('/admin/notices', [MessageController::class, 'sendAdminNotice'])
        ->middleware('throttle:30,1')
        ->name('admin.notices.send');
    Route::get('/admin/notices', [MessageController::class, 'getAdminNotices'])
        ->middleware('throttle:60,1')
        ->name('admin.notices.index');
    // Denunciar un post
    Route::post('/posts/{postId}/report', [MessageController::class, 'reportPost'])
        ->middleware('throttle:10,1')
        ->name('posts.report');

    // Rutas para reportar usuarios y eliminar conversaciones
    Route::post('/messages/report-user', [MessageController::class, 'reportUser'])
        ->middleware('throttle:10,1')
        ->name('messages.report-user');
    Route::delete('/messages/conversations/{conversationId}', [MessageController::class, 'deleteConversation'])
        ->middleware('throttle:30,1')
        ->name('messages.delete-conversation');
    
    // Rutas de admin para gestionar denuncias de usuarios
    Route::get('/messages/reports', [MessageController::class, 'getAllReports'])
        ->middleware('throttle:30,1')
        ->name('messages.reports.index');
    Route::get('/messages/reports/{reportId}', [MessageController::class, 'getReportDetails'])
        ->middleware('throttle:30,1')
        ->name('messages.reports.show');
    Route::put('/messages/reports/{reportId}/approve', [MessageController::class, 'approveReport'])
        ->middleware('throttle:30,1')
        ->name('messages.reports.approve');
    Route::put('/messages/reports/{reportId}/reject', [MessageController::class, 'rejectReport'])
        ->middleware('throttle:30,1')
        ->name('messages.reports.reject');

    // Rutas de pagos y contrataciones
    Route::get('/payments/stripe-key', [PaymentController::class, 'getStripePublicKey'])
        ->name('payments.stripe-key');
    Route::post('/payments/create-intent', [PaymentController::class, 'createPaymentIntent'])
        ->middleware('throttle:30,1')
        ->name('payments.create-intent');
    Route::post('/payments/create-checkout-session', [PaymentController::class, 'createCheckoutSession'])
        ->middleware('throttle:30,1')
        ->name('payments.create-checkout');
    Route::post('/payments/confirm', [PaymentController::class, 'confirmPayment'])
        ->middleware('throttle:30,1')
        ->name('payments.confirm');
    Route::post('/payments/confirm-checkout', [PaymentController::class, 'confirmCheckoutSession'])
        ->middleware('throttle:30,1')
        ->name('payments.confirm-checkout');
    
    // Rutas de contrataciones (bookings)
    Route::get('/bookings', [PaymentController::class, 'getBookings'])
        ->middleware('throttle:60,1')
        ->name('bookings.index');
    Route::get('/bookings/{bookingId}', [PaymentController::class, 'getBookingDetails'])
        ->middleware('throttle:60,1')
        ->name('bookings.show');
    Route::post('/bookings/{bookingId}/cancel', [PaymentController::class, 'cancelBooking'])
        ->middleware('throttle:30,1')
        ->name('bookings.cancel');
    Route::post('/bookings/{bookingId}/accept', [PaymentController::class, 'acceptBooking'])
        ->middleware('throttle:30,1')
        ->name('bookings.accept');
    Route::post('/bookings/{bookingId}/reject', [PaymentController::class, 'rejectBooking'])
        ->middleware('throttle:30,1')
        ->name('bookings.reject');
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
    Route::post('/users/{userId}/message', [ReportController::class, 'sendMessage']);
    
    // Gestión de denuncias
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{reportId}', [ReportController::class, 'show']);
    Route::post('/reports/{reportId}/approve', [ReportController::class, 'approve']);
    Route::post('/reports/{reportId}/reject', [ReportController::class, 'reject']);
    
    // Gestión de publicaciones
    Route::get('/posts', [AdminController::class, 'getPosts']);
    Route::delete('/posts/{id}', [AdminController::class, 'deletePost']);
});
