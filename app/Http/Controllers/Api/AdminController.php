<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * Obtiene estadísticas del dashboard de administración
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStats()
    {
        $stats = $this->adminService->getDashboardStats();
        return response()->json($stats);
    }

    /**
     * Obtiene la lista de solicitudes de profesores pendientes
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendingTeachers()
    {
        $requests = $this->adminService->getPendingTeacherRequests();
        return response()->json(['data' => $requests]);
    }

    /**
     * Aprueba una solicitud de profesor
     * 
     * @param Request $request
     * @param int $id ID de la solicitud
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveTeacher(Request $request, $id)
    {
        try {
            $teacherRequest = $this->adminService->approveTeacherRequest(
                $id,
                $request->user()->id
            );

            return response()->json([
                'message' => 'Profesor aprobado exitosamente',
                'data' => $teacherRequest
            ]);
        } catch (\Exception $e) {
            // Registrar el error completo en los logs
            Log::error('Error approving teacher request', [
                'request_id' => $id,
                'admin_id' => $request->user()->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Solo devolver mensajes específicos para errores de negocio conocidos
            $code = $e->getCode();
            if ($code === 400) {
                return response()->json([
                    'message' => $e->getMessage()
                ], 400);
            }

            // Para cualquier otro error, devolver mensaje genérico
            return response()->json([
                'message' => 'Error al aprobar la solicitud. Por favor, intenta nuevamente.'
            ], 500);
        }
    }

    /**
     * Rechaza una solicitud de profesor
     * 
     * @param Request $request
     * @param int $id ID de la solicitud
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectTeacher(Request $request, $id)
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->adminService->rejectTeacherRequest(
                $id,
                $request->user()->id,
                $validated['admin_notes'] ?? null
            );

            return response()->json([
                'message' => 'Solicitud rechazada'
            ]);
        } catch (\Exception $e) {
            // Registrar el error completo en los logs
            Log::error('Error rejecting teacher request', [
                'request_id' => $id,
                'admin_id' => $request->user()->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Solo devolver mensajes específicos para errores de negocio conocidos
            $code = $e->getCode();
            if ($code === 400) {
                return response()->json([
                    'message' => $e->getMessage()
                ], 400);
            }

            // Para cualquier otro error, devolver mensaje genérico
            return response()->json([
                'message' => 'Error al rechazar la solicitud. Por favor, intenta nuevamente.'
            ], 500);
        }
    }

    /**
     * Obtiene la lista de usuarios con filtros y paginación
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers(Request $request)
    {
        $filters = [
            'role' => $request->get('role'),
            'search' => $request->get('search'),
            'sortBy' => $request->get('sortBy', 'created_at'),
            'sortOrder' => $request->get('sortOrder', 'desc'),
            'perPage' => $request->get('perPage', 10),
        ];

        $users = $this->adminService->getUsersList($filters);
        return response()->json($users);
    }

    /**
     * Actualiza la información de un usuario
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUser(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'role' => 'sometimes|in:user,teacher,admin',
        ]);

        try {
            $user = $this->adminService->updateUser($id, $validated);

            return response()->json([
                'message' => 'User updated successfully',
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            // Registrar el error completo en los logs
            Log::error('Error updating user', [
                'user_id' => $id,
                'admin_id' => $request->user()->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Devolver mensaje genérico para errores del sistema
            return response()->json([
                'message' => 'Error al actualizar el usuario. Por favor, intenta nuevamente.'
            ], 500);
        }
    }

    /**
     * Elimina un usuario
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUser(Request $request, $id)
    {
        try {
            $this->adminService->deleteUser($id, $request->user()->id);

            return response()->json([
                'message' => 'User deleted successfully',
            ]);
        } catch (\Exception $e) {
            // Registrar el error completo en los logs
            Log::error('Error deleting user', [
                'user_id' => $id,
                'admin_id' => $request->user()->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Solo devolver mensajes específicos para errores de negocio conocidos
            $code = $e->getCode();
            if ($code === 403) {
                return response()->json([
                    'message' => $e->getMessage()
                ], 403);
            }

            // Para cualquier otro error, devolver mensaje genérico
            return response()->json([
                'message' => 'Error al eliminar el usuario. Por favor, intenta nuevamente.'
            ], 500);
        }
    }

    /**
     * Obtiene la lista de publicaciones para moderación
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPosts()
    {
        // TODO: Implementar cuando tengamos tabla de posts
        return response()->json([
            'posts' => [],
        ]);
    }

    /**
     * Elimina una publicación
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletePost($id)
    {
        // TODO: Implementar cuando tengamos tabla de posts
        return response()->json([
            'message' => 'Post deleted successfully',
        ]);
    }
}
