<?php

namespace App\Services;

use App\Models\Post;
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
            'totalPosts' => Post::count(),
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
        return DB::transaction(function () use ($requestId, $reviewerId) {
            // Obtener y bloquear el registro para evitar race conditions
            $teacherRequest = TeacherRequest::with('user')
                ->where('id', $requestId)
                ->lockForUpdate()
                ->first();

            if (!$teacherRequest) {
                throw new \Exception('Solicitud no encontrada', 404);
            }

            // Re-verificar el estado dentro de la transacción después del bloqueo
            if ($teacherRequest->status !== 'pending') {
                throw new \Exception('Esta solicitud ya fue procesada', 400);
            }

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

            // Devolver el registro actualizado con la relación user cargada
            return $teacherRequest->fresh('user');
        });
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
        return DB::transaction(function () use ($requestId, $reviewerId, $adminNotes) {
            // Obtener y bloquear el registro para evitar race conditions
            $teacherRequest = TeacherRequest::with('user')
                ->where('id', $requestId)
                ->lockForUpdate()
                ->first();

            if (!$teacherRequest) {
                throw new \Exception('Solicitud no encontrada', 404);
            }

            // Re-verificar el estado dentro de la transacción después del bloqueo
            if ($teacherRequest->status !== 'pending') {
                throw new \Exception('Esta solicitud ya fue procesada', 400);
            }

            // Actualizar solicitud
            $teacherRequest->update([
                'status' => 'rejected',
                'admin_notes' => $adminNotes,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now()
            ]);

            // Actualizar usuario
            $teacherRequest->user->update(['teacher_status' => 'rejected']);

            // Devolver el registro actualizado con la relación user cargada
            return $teacherRequest->fresh('user');
        });
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

        // Validar y sanitizar sortBy - Lista blanca de columnas permitidas
        $allowedSortColumns = ['id', 'name', 'email', 'role', 'created_at', 'updated_at'];
        $sortBy = $filters['sortBy'] ?? 'created_at';
        if (!in_array($sortBy, $allowedSortColumns, true)) {
            $sortBy = 'created_at';
        }

        // Validar y normalizar sortOrder - Solo 'asc' o 'desc'
        $sortOrder = strtolower($filters['sortOrder'] ?? 'desc');
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'desc';
        }

        $query->orderBy($sortBy, $sortOrder);

        // Validar y limitar perPage - Rango de 1 a 100
        $perPage = isset($filters['perPage']) ? (int)$filters['perPage'] : 10;
        $perPage = max(1, min(100, $perPage));

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
