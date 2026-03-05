<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TeacherRequestService;
use Illuminate\Http\Request;

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
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
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
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }
}
