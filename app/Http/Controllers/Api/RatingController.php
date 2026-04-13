<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RatingController extends Controller
{
    /**
     * Calcular estadísticas de reseñas de un profesor
     */
    private function calculateTeacherStats(int $teacherId): array
    {
        $averageRating = Rating::where('teacher_id', $teacherId)->avg('rating');
        $totalCount = Rating::where('teacher_id', $teacherId)->count();
        
        return [
            'average' => round($averageRating ?? 0, 2),
            'count' => $totalCount,
        ];
    }

    /**
     * Crear una nueva reseña/calificación para un profesor
     * 
     * POST /api/teachers/{teacherId}/ratings
     */
    public function store(Request $request, $teacherId)
    {
        try {
            $user = $request->user();
            
            // Validar que el usuario es estudiante
            if ($user->role !== 'user') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo los estudiantes pueden crear reseñas',
                ], 403);
            }
            
            // Validar que el profesor existe
            $teacher = User::findOrFail($teacherId);
            
            // Validar que el usuario es profesor
            if (!$teacher->isTeacher()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario especificado no es un profesor',
                ], 400);
            }
            
            // Validar que no es el profesor a sí mismo
            if ($user->id === $teacher->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes calificar tu propio perfil',
                ], 400);
            }
            
            // Validar entrada
            $validated = $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'review' => 'nullable|string|max:1000',
            ]);
            
            // Crear o actualizar la reseña (si el usuario ya había dejado reseña, se actualiza)
            $rating = Rating::updateOrCreate(
                [
                    'teacher_id' => $teacherId,
                    'student_id' => $user->id,
                ],
                [
                    'rating' => $validated['rating'],
                    'review' => $validated['review'] ?? null,
                ]
            );
            
            // Calcular el nuevo promedio del profesor
            $stats = $this->calculateTeacherStats($teacherId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'rating' => $rating,
                    'average' => $stats['average'],
                    'total_count' => $stats['count'],
                ],
                'message' => 'Reseña guardada exitosamente',
            ], 201);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profesor no encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al crear reseña: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la reseña',
            ], 500);
        }
    }

    /**
     * Obtener todas las reseñas de un profesor
     * 
     * GET /api/teachers/{teacherId}/ratings
     */
    public function getTeacherRatings($teacherId)
    {
        try {
            // Validar que el profesor existe
            $teacher = User::findOrFail($teacherId);
            
            // Validar que es profesor
            if (!$teacher->isTeacher()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario especificado no es un profesor',
                ], 400);
            }
            
            // Obtener todas las reseñas del profesor con información del estudiante
            $ratings = Rating::where('teacher_id', $teacherId)
                ->with('student:id,name,avatar_path')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($rating) {
                    return [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'review' => $rating->review,
                        'student_name' => $rating->student->name,
                        'student_avatar_url' => $rating->student->avatar_url,
                        'student_id' => $rating->student->id,
                        'created_at' => $rating->created_at->toIso8601String(),
                    ];
                });
            
            // Calcular estadísticas
            $stats = $this->calculateTeacherStats($teacherId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'ratings' => $ratings,
                    'average' => $stats['average'],
                    'total_count' => $stats['count'],
                ],
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profesor no encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al obtener reseñas: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las reseñas',
            ], 500);
        }
    }

    /**
     * Actualizar una reseña existente
     * 
     * PUT /api/teachers/{teacherId}/ratings/{ratingId}
     */
    public function update(Request $request, $teacherId, $ratingId)
    {
        try {
            $user = $request->user();
            
            // Obtener la reseña
            $rating = Rating::findOrFail($ratingId);
            
            // Validar que la reseña pertenece al profesor especificado
            if ($rating->teacher_id != $teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'La reseña no pertenece a este profesor',
                ], 400);
            }
            
            // Validar que es el estudiante que creó la reseña
            if ($rating->student_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para actualizar esta reseña',
                ], 403);
            }
            
            // Validar entrada
            $validated = $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'review' => 'nullable|string|max:1000',
            ]);
            
            // Actualizar la reseña
            $rating->update([
                'rating' => $validated['rating'],
                'review' => $validated['review'] ?? null,
            ]);
            
            // Calcular el nuevo promedio del profesor
            $stats = $this->calculateTeacherStats($teacherId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'rating' => $rating,
                    'average' => $stats['average'],
                    'total_count' => $stats['count'],
                ],
                'message' => 'Reseña actualizada exitosamente',
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Reseña no encontrada',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al actualizar reseña: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la reseña',
            ], 500);
        }
    }

    /**
     * Eliminar una reseña
     * 
     * DELETE /api/teachers/{teacherId}/ratings/{ratingId}
     */
    public function destroy(Request $request, $teacherId, $ratingId)
    {
        try {
            $user = $request->user();
            
            // Obtener la reseña
            $rating = Rating::findOrFail($ratingId);
            
            // Validar que la reseña pertenece al profesor especificado
            if ($rating->teacher_id != $teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'La reseña no pertenece a este profesor',
                ], 400);
            }
            
            // Validar que es el estudiante que creó la reseña
            if ($rating->student_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar esta reseña',
                ], 403);
            }
            
            // Eliminar la reseña
            $rating->delete();
            
            // Calcular el nuevo promedio del profesor
            $stats = $this->calculateTeacherStats($teacherId);
            
            return response()->json([
                'success' => true,
                'message' => 'Reseña eliminada exitosamente',
                'data' => [
                    'average' => $stats['average'],
                    'total_count' => $stats['count'],
                ],
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Reseña no encontrada',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al eliminar reseña: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la reseña',
            ], 500);
        }
    }
}
