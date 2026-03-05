<?php

namespace App\Services;

use App\Models\TeacherRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TeacherRequestService
{
    /**
     * Crear una solicitud de profesor
     * 
     * @param User $user
     * @param array $data
     * @param \Illuminate\Http\UploadedFile $certificate
     * @return TeacherRequest
     * @throws \Exception
     */
    public function createRequest(User $user, array $data, $certificate): TeacherRequest
    {
        // Verificar si ya es profesor
        if ($user->role === 'teacher') {
            throw new \Exception('Ya eres profesor', 400);
        }

        $certificatePath = null;

        try {
            // Guardar certificado en disco privado
            $certificatePath = $certificate->store('certificates', 'local');

            // Transacción atómica: verificar existencia, crear solicitud y actualizar usuario
            $teacherRequest = DB::transaction(function () use ($user, $data, $certificatePath) {
                // Verificar solicitud pendiente DENTRO de la transacción para evitar race conditions
                $existingRequest = TeacherRequest::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if ($existingRequest) {
                    throw new \Exception('Ya tienes una solicitud pendiente de revisión', 400);
                }

                $request = TeacherRequest::create([
                    'user_id' => $user->id,
                    'subject' => $data['subject'],
                    'bio' => $data['bio'],
                    'price_per_hour' => $data['price_per_hour'] ?? null,
                    'certificate_path' => $certificatePath,
                    'status' => 'pending'
                ]);

                // Actualizar estado del usuario
                $user->update(['teacher_status' => 'pending']);

                return $request;
            });

            return $teacherRequest;
        } catch (\Exception $e) {
            // Limpiar certificado huérfano si la transacción falla
            if ($certificatePath) {
                Storage::disk('local')->delete($certificatePath);
            }
            throw $e;
        }
    }

    /**
     * Obtener el estado de la solicitud de un usuario
     * 
     * @param User $user
     * @return array
     */
    public function getRequestStatus(User $user): array
    {
        $teacherRequest = TeacherRequest::where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$teacherRequest) {
            return [
                'hasRequest' => false,
                'canApply' => $user->role !== 'teacher',
                'request' => null
            ];
        }

        // Alinear lógica con createRequest(): puede aplicar si no es profesor y no tiene solicitud pendiente
        return [
            'hasRequest' => true,
            'canApply' => $user->role !== 'teacher' && $teacherRequest->status !== 'pending',
            'request' => $teacherRequest
        ];
    }

    /**
     * Cancelar solicitud pendiente
     * 
     * @param User $user
     * @return bool
     * @throws \Exception
     */
    public function cancelRequest(User $user): bool
    {
        $teacherRequest = TeacherRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$teacherRequest) {
            throw new \Exception('No tienes ninguna solicitud pendiente', 404);
        }

        // Guardar ruta del certificado para eliminarlo después de la transacción
        $certificatePath = $teacherRequest->certificate_path;

        // Eliminar solicitud y actualizar usuario en transacción
        DB::transaction(function () use ($teacherRequest, $user) {
            $teacherRequest->delete();
            $user->update(['teacher_status' => null]);
        });

        // Eliminar certificado solo después de que la transacción se haya confirmado
        if ($certificatePath) {
            Storage::disk('local')->delete($certificatePath);
        }

        return true;
    }
}
