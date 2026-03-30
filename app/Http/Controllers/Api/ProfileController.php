<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Interest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Obtiene los intereses del usuario autenticado
     * 
     * GET /api/profile/interests
     */
    public function getInterests(Request $request)
    {
        try {
            $user = $request->user();
            
            // Obtener los intereses del usuario con sus IDs y nombres
            $interests = $user->interests()->get(['interests.id', 'interests.name', 'interests.description']);
            
            return response()->json([
                'success' => true,
                'data' => $interests,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener intereses: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener intereses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guarda o actualiza los intereses del usuario autenticado
     * 
     * PUT /api/profile/interests
     * 
     * Body esperado (cualquiera de estos formatos):
     * {
     *     "interests": [1, 2, 3]  // Array de IDs de intereses
     * }
     * O:
     * {
     *     "interests": ["Programación", "Diseño"]  // Array de nombres de intereses
     * }
     */
    public function updateInterests(Request $request)
    {
        try {
            $user = $request->user();
            
            Log::info('updateInterests called', [
                'user_id' => $user->id,
                'request_all' => $request->all(),
                'content_type' => $request->header('Content-Type'),
            ]);
            
            // Obtener el array de intereses desde diferentes posibles fuentes
            $interestsArray = $request->input('interests', []);

            // Si viene vacío, intentar otra cosa
            if (empty($interestsArray)) {
                Log::warning('interestsArray vacío, intentando alternativas', [
                    'request_all' => $request->all(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Campo "interests" requerido',
                    'debug' => [
                        'received' => $request->all(),
                        'expected_format' => ['interests' => [1, 2, 3]],
                    ],
                ], 422);
            }

            // Convertir a array si no lo es
            if (!is_array($interestsArray)) {
                $interestsArray = [$interestsArray];
            }

            Log::info('Procesando intereses', ['count' => count($interestsArray), 'array' => $interestsArray]);

            // Convertir nombres a IDs si es necesario
            $interestIds = [];
            foreach ($interestsArray as $interest) {
                if (is_numeric($interest)) {
                    // Es un ID
                    $interestIds[] = (int)$interest;
                } else {
                    // Es un nombre, buscar el ID
                    $interestName = trim((string)$interest);
                    $interestModel = Interest::where('name', $interestName)->first();
                    
                    if (!$interestModel) {
                        Log::warning("Interés no encontrado: '$interestName'", [
                            'available' => Interest::pluck('name')->toArray(),
                        ]);
                        
                        return response()->json([
                            'success' => false,
                            'message' => "El interés '$interestName' no existe",
                            'debug' => [
                                'searched_name' => $interestName,
                                'available_interests' => Interest::pluck('name')->toArray(),
                            ],
                        ], 422);
                    }
                    $interestIds[] = $interestModel->id;
                }
            }

            Log::info('Interest IDs resueltos', ['ids' => $interestIds]);

            // Validar que todos los IDs sean válidos
            $invalidIds = [];
            foreach ($interestIds as $id) {
                if (!Interest::find($id)) {
                    $invalidIds[] = $id;
                }
            }

            if (!empty($invalidIds)) {
                Log::error('IDs de intereses inválidos encontrados', ['invalid_ids' => $invalidIds]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Algunos intereses no existen',
                    'invalid_ids' => $invalidIds,
                ], 422);
            }

            // Sincronizar los intereses del usuario
            $user->interests()->sync($interestIds);

            // Obtener los intereses actualizados
            $updatedInterests = $user->interests()->get(['interests.id', 'interests.name', 'interests.description']);

            Log::info('Intereses actualizados exitosamente', [
                'user_id' => $user->id,
                'interest_count' => count($updatedInterests),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Intereses actualizados correctamente',
                'data' => $updatedInterests,
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error en updateInterests', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar intereses',
                'error' => $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], 500);
        }
    }

    /**
     * Obtiene la lista de todos los intereses disponibles
     * 
     * GET /api/interests
     * (Endpoint público o protegido según necesidad)
     */
    public function listAllInterests()
    {
        try {
            $interests = Interest::select('interests.id', 'interests.name', 'interests.description')
                ->orderBy('interests.name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $interests,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener listado de intereses: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener intereses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
