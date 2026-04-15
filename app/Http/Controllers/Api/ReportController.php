<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use App\Models\RatingReport;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    /**
     * GET /api/admin/reports
     * Listar todas las denuncias con filtros
     */
    public function index(Request $request)
    {
        try {
            Log::info('Fetching reports - Request params:', $request->all());

            $query = RatingReport::query();

            // Cargar relaciones
            $query->with(['rating', 'teacher', 'reporter']);

            // Filtrar por estado - solo si se especifica
            if ($request->has('status') && $request->input('status') !== '' && $request->input('status') !== 'all') {
                $statusValue = $request->input('status');
                Log::info('Filtering by status: ' . $statusValue);
                $query->where('status', $statusValue);
            }

            // Filtrar por razón - solo si se especifica
            if ($request->has('reason') && $request->input('reason') !== '' && $request->input('reason') !== 'all') {
                $reasonValue = $request->input('reason');
                Log::info('Filtering by reason: ' . $reasonValue);
                $query->where('reason', $reasonValue);
            }

            // Ordenar
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Logs para debug
            Log::info('Query SQL: ' . $query->toSql());
            Log::info('Query bindings:', $query->getBindings());

            // Paginar
            $perPage = (int)$request->input('per_page', 15);
            $page = (int)$request->input('page', 1);
            
            $reports = $query->paginate($perPage, ['*'], 'page', $page);

            Log::info('Reports found: ' . $reports->total());

            // Transformar los datos para que 'reporter' sea 'reported_user'
            $transformedReports = $reports->items();
            $transformedData = array_map(function ($report) {
                $reportArray = $report->toArray();
                if (isset($reportArray['reporter'])) {
                    $reportArray['reported_user'] = $reportArray['reporter'];
                    unset($reportArray['reporter']);
                }
                return $reportArray;
            }, $transformedReports);

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'total' => $reports->total(),
                'per_page' => $reports->perPage(),
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'from' => $reports->firstItem(),
                'to' => $reports->lastItem(),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching reports: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener denuncias: ' . $e->getMessage(),
                'debug' => env('APP_DEBUG') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * GET /api/admin/reports/{reportId}
     * Obtener detalles de una denuncia específica
     */
    public function show(Request $request, $reportId)
    {
        try {
            $report = RatingReport::findOrFail($reportId)
                ->load(['rating', 'teacher', 'reporter']);

            // Transformar los datos
            $reportArray = $report->toArray();
            if (isset($reportArray['reporter'])) {
                $reportArray['reported_user'] = $reportArray['reporter'];
                unset($reportArray['reporter']);
            }

            return response()->json([
                'success' => true,
                'data' => $reportArray,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Denuncia no encontrada',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching report: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener denuncia: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/admin/reports/{reportId}/approve
     * Aprobar una denuncia (elimina la reseña)
     */
    public function approve(Request $request, $reportId)
    {
        try {
            $report = RatingReport::findOrFail($reportId);

            // Validar que está pendiente
            if ($report->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo puedes aprobar denuncias pendientes',
                ], 400);
            }

            // Validar entrada
            $validated = $request->validate([
                'admin_notes' => 'nullable|string|max:5000',
            ]);

            // Obtener la reseña antes de eliminarla
            $rating = $report->rating;
            if (!$rating) {
                return response()->json([
                    'success' => false,
                    'message' => 'La reseña no existe',
                ], 404);
            }

            // Obtener datos necesarios para mensajes
            $reviewAuthor = $rating->student; // Quien escribió la reseña
            $reporter = $report->reporter;     // Quien denunció
            $adminNotes = $validated['admin_notes'] ?? null;

            // Eliminar la reseña
            $rating->delete();

            // Actualizar el reporte con status y luego eliminarlo
            $report->update([
                'status' => 'approved',
                'admin_notes' => $adminNotes,
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            
            // Eliminar la denuncia del sistema (ya fue procesada)
            $reportId = $report->id;
            $report->delete();

            // ========== PENALIZACIONES ==========

            // 1. PENALIZAR al autor de la reseña (quien recibe el castigo)
            if ($reviewAuthor) {
                $reviewAuthor->increment('penalties');
                $reviewAuthor->update([
                    'last_penalty_reason' => 'Reseña eliminada por violación de normas',
                    'last_penalty_at' => now(),
                ]);
                Log::info("Penalización aplicada a usuario {$reviewAuthor->id} - Total penalties: {$reviewAuthor->penalties}");
            }

            return response()->json([
                'success' => true,
                'message' => 'Denuncia aprobada. Reseña eliminada y penalizaciones aplicadas.',
                'data' => [
                    'id' => $report->id,
                    'status' => 'approved',
                    'admin_notes' => $adminNotes,
                    'penalties_applied' => 1,
                    'review_author_id' => $reviewAuthor?->id,
                    'review_author_penalties' => $reviewAuthor?->penalties ?? 0,
                    'updated_at' => now(),
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Denuncia no encontrada',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error approving report: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar denuncia: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/admin/reports/{reportId}/reject
     * Rechazar una denuncia (mantiene la reseña)
     */
    public function reject(Request $request, $reportId)
    {
        try {
            $report = RatingReport::findOrFail($reportId);

            // Validar que está pendiente
            if ($report->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo puedes rechazar denuncias pendientes',
                ], 400);
            }

            // Validar entrada
            $validated = $request->validate([
                'admin_notes' => 'nullable|string|max:5000',
            ]);

            // Obtener datos necesarios para mensajes
            $reporter = $report->reporter;           // Quien denunció (recibe penalización)
            $reviewAuthor = $report->rating->student; // Autor de la reseña (tranquilidad)
            $adminNotes = $validated['admin_notes'] ?? null;

            // Actualizar el reporte con status y luego eliminarlo
            $report->update([
                'status' => 'rejected',
                'admin_notes' => $adminNotes,
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            
            // Eliminar la denuncia del sistema (ya fue procesada)
            $reportId = $report->id;
            $report->delete();

            // ========== PENALIZACIONES ==========

            // 1. PENALIZAR al que denunció (denuncia falsa)
            if ($reporter) {
                $reporter->increment('penalties');
                $reporter->update([
                    'last_penalty_reason' => 'Denuncia rechazada - denuncias falsas',
                    'last_penalty_at' => now(),
                ]);
                Log::info("Penalización aplicada a denunciante {$reporter->id} - Total penalties: {$reporter->penalties}");
            }

            return response()->json([
                'success' => true,
                'message' => 'Denuncia rechazada y penalizaciones aplicadas.',
                'data' => [
                    'id' => $report->id,
                    'status' => 'rejected',
                    'admin_notes' => $adminNotes,
                    'penalties_applied_to_reporter' => 1,
                    'reporter_id' => $reporter?->id,
                    'reporter_penalties' => $reporter?->penalties ?? 0,
                    'updated_at' => now(),
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Denuncia no encontrada',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error rejecting report: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar denuncia: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener etiqueta legible de la razón de denuncia
     */
    private function getReasonLabel($reason)
    {
        $labels = [
            'offensive_content' => 'Contenido Ofensivo',
            'spam' => 'Spam',
            'fake_review' => 'Reseña Falsa',
            'inappropriate' => 'Inapropiado',
            'other' => 'Otro',
        ];
        return $labels[$reason] ?? ucfirst(str_replace('_', ' ', $reason));
    }

    /**
     * POST /api/admin/users/{userId}/message
     * Enviar mensaje a un usuario (solo admin)
     */
    public function sendMessage(Request $request, $userId)
    {
        try {
            // Validar que el usuario existe
            $toUser = User::findOrFail($userId);

            // Validar entrada
            $validated = $request->validate([
                'subject' => 'required|string|max:255',
                'message' => 'required|string|max:5000',
            ]);

            // Crear el mensaje
            $message = Message::create([
                'from_user_id' => $request->user()->id,
                'to_user_id' => $userId,
                'subject' => $validated['subject'],
                'body' => $validated['message'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mensaje enviado exitosamente',
                'data' => [
                    'id' => $message->id,
                    'from_user_id' => $message->from_user_id,
                    'to_user_id' => $message->to_user_id,
                    'subject' => $message->subject,
                    'body' => $message->body,
                    'read_at' => $message->read_at,
                    'created_at' => $message->created_at,
                ],
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error sending message: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar mensaje: ' . $e->getMessage(),
            ], 500);
        }
    }
}
