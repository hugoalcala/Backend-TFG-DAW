<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Obtiene estadísticas del dashboard de administración
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStats()
    {
        $totalUsers = User::count();
        $totalTeachers = User::where('role', 'teacher')->count();
        $pendingTeachers = 0; // TODO: Implementar cuando tengamos tabla de solicitudes
        $totalPosts = 0; // TODO: Implementar cuando tengamos tabla de posts

        return response()->json([
            'totalUsers' => $totalUsers,
            'totalTeachers' => $totalTeachers,
            'pendingTeachers' => $pendingTeachers,
            'totalPosts' => $totalPosts,
        ]);
    }

    /**
     * Obtiene la lista de solicitudes de profesores pendientes
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendingTeachers()
    {
        // TODO: Implementar cuando tengamos tabla de solicitudes de profesores
        // Por ahora retornamos un array vacío
        return response()->json([
            'pendingTeachers' => [],
        ]);
    }

    /**
     * Aprueba una solicitud de profesor
     * 
     * @param int $id ID de la solicitud
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveTeacher($id)
    {
        // TODO: Implementar cuando tengamos tabla de solicitudes
        // Por ahora solo actualizamos el rol del usuario si existe
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $user->update(['role' => 'teacher']);

        return response()->json([
            'message' => 'Teacher approved successfully',
            'user' => $user,
        ]);
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
            'reason' => 'nullable|string|max:500',
        ]);

        // TODO: Implementar cuando tengamos tabla de solicitudes
        // Por ahora solo retornamos éxito
        
        return response()->json([
            'message' => 'Teacher request rejected',
            'reason' => $validated['reason'] ?? 'No reason provided',
        ]);
    }

    /**
     * Obtiene la lista de usuarios con filtros y paginación
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers(Request $request)
    {
        $query = User::query();

        // Filtrar por rol si se proporciona
        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // Buscar por nombre o email
        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        // Ordenar
        $sortBy = $request->get('sortBy', 'created_at');
        $sortOrder = $request->get('sortOrder', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginar
        $perPage = $request->get('perPage', 10);
        $users = $query->paginate($perPage);

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
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'role' => 'sometimes|in:user,teacher,admin',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user,
        ]);
    }

    /**
     * Elimina un usuario
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        // No permitir que un admin se elimine a sí mismo
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
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
