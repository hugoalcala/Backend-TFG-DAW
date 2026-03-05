<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeacherRequestController extends Controller
{
    /**
     * Crear una solicitud para convertirse en profesor
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:500', // Acepta múltiples materias separadas por comas
            'bio' => 'required|string|max:1000',
            'price_per_hour' => 'nullable|numeric|min:0|max:9999.99',
            'certificate' => 'required|file|mimes:pdf|max:5120' // Solo PDF, máx 5MB
        ]);

        $user = $request->user();

        // Verificar si ya es profesor
        if ($user->role === 'teacher') {
            return response()->json([
                'message' => 'Ya eres profesor'
            ], 400);
        }

        // Verificar si ya tiene solicitud pendiente
        $existingRequest = TeacherRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return response()->json([
                'message' => 'Ya tienes una solicitud pendiente de revisión'
            ], 400);
        }

        // Guardar certificado
        $certificatePath = $request->file('certificate')->store('certificates', 'public');

        // Crear solicitud
        $teacherRequest = TeacherRequest::create([
            'user_id' => $user->id,
            'subject' => $validated['subject'],
            'bio' => $validated['bio'],
            'price_per_hour' => $validated['price_per_hour'] ?? null,
            'certificate_path' => $certificatePath,
            'status' => 'pending'
        ]);

        // Actualizar estado del usuario
        $user->update(['teacher_status' => 'pending']);

        return response()->json([
            'message' => 'Solicitud enviada exitosamente',
            'data' => [
                'user' => $user->fresh(),
                'request' => $teacherRequest
            ]
        ], 201);
    }

    /**
     * Obtener el estado de la solicitud del usuario autenticado
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        $user = $request->user();
        
        $teacherRequest = TeacherRequest::where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$teacherRequest) {
            return response()->json([
                'hasRequest' => false,
                'canApply' => $user->role !== 'teacher'
            ]);
        }

        return response()->json([
            'hasRequest' => true,
            'canApply' => false,
            'request' => $teacherRequest
        ]);
    }

    /**
     * Cancelar solicitud pendiente
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(Request $request)
    {
        $user = $request->user();
        
        $teacherRequest = TeacherRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$teacherRequest) {
            return response()->json([
                'message' => 'No tienes ninguna solicitud pendiente'
            ], 404);
        }

        // Eliminar certificado
        if ($teacherRequest->certificate_path) {
            Storage::disk('public')->delete($teacherRequest->certificate_path);
        }

        $teacherRequest->delete();
        $user->update(['teacher_status' => null]);

        return response()->json([
            'message' => 'Solicitud cancelada exitosamente'
        ]);
    }
}
