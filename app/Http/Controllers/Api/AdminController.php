<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\TeacherRequest;

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

    /**
     * Descarga el certificado de una solicitud de profesor
     * 
     * @param int $id ID de la solicitud
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function downloadCertificate($id)
    {
        // Validar que el ID sea un número válido
        if (!is_numeric($id) || intval($id) <= 0) {
            return response()->json([
                'message' => 'ID de solicitud inválido'
            ], 400);
        }

        try {
            // Buscar la solicitud de profesor
            $teacherRequest = TeacherRequest::find($id);

            if (!$teacherRequest) {
                return response()->json([
                    'message' => 'Solicitud no encontrada'
                ], 404);
            }

            // Validar el path del certificado por seguridad
            $certificatePath = $teacherRequest->certificate_path;

            // Validar que el path no esté vacío
            if (empty($certificatePath)) {
                Log::error('Certificate path is empty', ['request_id' => $id]);
                return response()->json([
                    'message' => 'Ruta del certificado inválida'
                ], 400);
            }

            // Validar que termine en .pdf
            if (!str_ends_with(strtolower($certificatePath), '.pdf')) {
                Log::error('Certificate path invalid extension', [
                    'request_id' => $id,
                    'certificate_path' => $certificatePath
                ]);
                return response()->json([
                    'message' => 'Formato de certificado inválido'
                ], 400);
            }

            // Validar que no contenga segmentos ascendentes (..) o rutas absolutas
            if (str_contains($certificatePath, '..') || 
                str_starts_with($certificatePath, '/') || 
                preg_match('/^[a-zA-Z]:/', $certificatePath)) {
                Log::error('Certificate path contains invalid segments', [
                    'request_id' => $id,
                    'certificate_path' => $certificatePath
                ]);
                return response()->json([
                    'message' => 'Ruta del certificado inválida'
                ], 400);
            }

            // Normalizar el path: asegurar que esté dentro del directorio certificates
            $normalizedPath = str_replace('\\', '/', $certificatePath);
            if (!str_starts_with($normalizedPath, 'certificates/')) {
                Log::error('Certificate path not in expected directory', [
                    'request_id' => $id,
                    'certificate_path' => $certificatePath
                ]);
                return response()->json([
                    'message' => 'Ruta del certificado inválida'
                ], 400);
            }

            // Verificar que el certificado existe en el storage
            if (!Storage::disk('local')->exists($certificatePath)) {
                Log::error('Certificate file not found', [
                    'request_id' => $id,
                    'certificate_path' => $certificatePath
                ]);

                return response()->json([
                    'message' => 'Certificado no encontrado'
                ], 404);
            }

            // Obtener el path completo del archivo de forma segura
            $filePath = Storage::disk('local')->path($certificatePath);

            // Devolver el archivo como respuesta
            return response()->file($filePath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="certificado_' . $id . '.pdf"'
            ]);
        } catch (\Exception $e) {
            Log::error('Error downloading certificate', [
                'request_id' => $id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al descargar el certificado'
            ], 500);
        }
    }
}
