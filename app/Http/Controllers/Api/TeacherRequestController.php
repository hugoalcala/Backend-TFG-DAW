<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TeacherRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeacherRequestController extends Controller
{
    protected $teacherRequestService;

    public function __construct(TeacherRequestService $teacherRequestService)
    {
        $this->teacherRequestService = $teacherRequestService;
    }

    /**
     * Crear una solicitud para convertirse en profesor
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:500',
            'bio' => 'required|string|max:1000',
            'price_per_hour' => 'nullable|numeric|min:0|max:9999.99',
            'certificate' => 'required|file|mimes:pdf|max:5120'
        ]);

        try {
            $teacherRequest = $this->teacherRequestService->createRequest(
                $request->user(),
                $validated,
                $request->file('certificate')
            );

            return response()->json([
                'message' => 'Solicitud enviada exitosamente',
                'data' => [
                    'user' => $request->user()->fresh(),
                    'request' => $teacherRequest
                ]
            ], 201);
        } catch (\Exception $e) {
            // Registrar el error completo en los logs
            Log::error('Error creating teacher request', [
                'user_id' => $request->user()->id,
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
                'message' => 'Error al procesar la solicitud. Por favor, intenta nuevamente.'
            ], 500);
        }
    }

    /**
     * Obtener el estado de la solicitud del usuario autenticado
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        $status = $this->teacherRequestService->getRequestStatus($request->user());
        return response()->json($status);
    }

    /**
     * Cancelar solicitud pendiente
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(Request $request)
    {
        try {
            $this->teacherRequestService->cancelRequest($request->user());
            
            return response()->json([
                'message' => 'Solicitud cancelada exitosamente'
            ]);
        } catch (\Exception $e) {
            // Registrar el error completo en los logs
            Log::error('Error cancelling teacher request', [
                'user_id' => $request->user()->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Solo devolver mensajes específicos para errores de negocio conocidos
            $code = $e->getCode();
            if ($code === 404) {
                return response()->json([
                    'message' => $e->getMessage()
                ], 404);
            }

            // Para cualquier otro error, devolver mensaje genérico
            return response()->json([
                'message' => 'Error al cancelar la solicitud. Por favor, intenta nuevamente.'
            ], 500);
        }
    }
}
