<?php

namespace App\Services;

use App\Models\TeacherRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminService
{
    /**
     * Obtener estadísticas del dashboard
     * 
     * @return array
     */
    public function getDashboardStats(): array
    {
        return [
            'totalUsers' => User::count(),
            'totalTeachers' => User::where('role', 'teacher')->count(),
            'pendingTeachers' => TeacherRequest::where('status', 'pending')->count(),
            'totalPosts' => 0, // TODO: Implementar cuando tengamos tabla de posts
        ];
    }

    /**
     * Obtener solicitudes de profesores pendientes
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingTeacherRequests()
    {
        return TeacherRequest::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Aprobar solicitud de profesor
     * 
     * @param int $requestId
     * @param int $reviewerId
     * @return TeacherRequest
     * @throws \Exception
     */
    public function approveTeacherRequest(int $requestId, int $reviewerId): TeacherRequest
    {
        $teacherRequest = TeacherRequest::findOrFail($requestId);
        
        if ($teacherRequest->status !== 'pending') {
            throw new \Exception('Esta solicitud ya fue procesada', 400);
        }

        DB::transaction(function () use ($teacherRequest, $reviewerId) {
            // Actualizar usuario a profesor
            $teacherRequest->user->update([
                'role' => 'teacher',
                'teacher_status' => 'approved',
                'subject' => $teacherRequest->subject,
                'bio' => $teacherRequest->bio,
                'price_per_hour' => $teacherRequest->price_per_hour
            ]);

            // Actualizar solicitud
            $teacherRequest->update([
                'status' => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now()
            ]);
        });

        return $teacherRequest->fresh('user');
    }

    /**
     * Rechazar solicitud de profesor
     * 
     * @param int $requestId
     * @param int $reviewerId
     * @param string|null $adminNotes
     * @return TeacherRequest
     * @throws \Exception
     */
    public function rejectTeacherRequest(int $requestId, int $reviewerId, ?string $adminNotes = null): TeacherRequest
    {
        $teacherRequest = TeacherRequest::findOrFail($requestId);
        
        if ($teacherRequest->status !== 'pending') {
            throw new \Exception('Esta solicitud ya fue procesada', 400);
        }

        $teacherRequest->update([
            'status' => 'rejected',
            'admin_notes' => $adminNotes,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now()
        ]);

        $teacherRequest->user->update(['teacher_status' => 'rejected']);

        return $teacherRequest;
    }

    /**
     * Obtener lista de usuarios con filtros
     * 
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getUsersList(array $filters = [])
    {
        $query = User::query();

        // Filtrar por rol
        if (!empty($filters['role']) && $filters['role'] !== 'all') {
            $query->where('role', $filters['role']);
        }

        // Buscar por nombre o email
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Ordenar
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginar
        $perPage = $filters['perPage'] ?? 10;
        return $query->paginate($perPage);
    }

    /**
     * Actualizar usuario
     * 
     * @param int $userId
     * @param array $data
     * @return User
     * @throws \Exception
     */
    public function updateUser(int $userId, array $data): User
    {
        $user = User::findOrFail($userId);
        $user->update($data);
        return $user->fresh();
    }

    /**
     * Eliminar usuario
     * 
     * @param int $userId
     * @param int $currentAdminId
     * @return bool
     * @throws \Exception
     */
    public function deleteUser(int $userId, int $currentAdminId): bool
    {
        $user = User::findOrFail($userId);

        // No permitir que un admin se elimine a sí mismo
        if ($user->id === $currentAdminId) {
            throw new \Exception('No puedes eliminar tu propia cuenta', 403);
        }

        return $user->delete();
    }
}
