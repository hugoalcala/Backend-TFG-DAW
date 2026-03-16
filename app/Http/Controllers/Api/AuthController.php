<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Registra un nuevo usuario en la aplicación
     * 
     * Valida los datos del usuario (nombre, email, contraseña)
     * Crea el usuario en la base de datos con contraseña hasheada
     * Genera y retorna un token de autenticación
     * 
     * @param Request $request Contiene name, email, password y password_confirmation
     * @return \Illuminate\Http\JsonResponse Usuario creado y token de acceso
     */
    public function register(Request $request)
    {
        $payload = $this->normalizeRegistrationPayload($request);

        Log::info('Register iniciado', [
            'request_data' => [
                'name' => $payload['name'] ?? null,
                'email' => $payload['email'] ?? null,
            ],
        ]);

        // Validar datos de entrada
        // - name: requerido, string, máximo 255 caracteres
        // - email: requerido, email válido, único en la tabla users
        // - password: requerido, mínimo 8 caracteres, debe ser confirmado
        $validated = validator($payload, [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ])->validate();

        // Crear nuevo usuario en la base de datos con contraseña encriptada
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']), // Hash seguro de la contraseña
        ]);

        // Retornar respuesta con usuario creado y token de acceso
        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken,
            'role' => $user->role,
        ], 201); // Status 201: Created
    }

    /**
     * Normaliza variantes del payload de registro enviadas por el frontend.
     */
    private function normalizeRegistrationPayload(Request $request): array
    {
        $name = trim((string) $request->input('name', ''));

        if ($name === '') {
            $firstName = trim((string) ($request->input('first_name') ?? $request->input('nombre') ?? ''));
            $lastName = trim((string) ($request->input('last_name') ?? $request->input('apellido') ?? ''));
            $name = trim($firstName.' '.$lastName);
        }

        return [
            'name' => $name,
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation')
                ?? $request->input('confirm_password')
                ?? $request->input('confirmPassword'),
        ];
    }

    /**
     * Autentica un usuario con sus credenciales
     * 
     * Valida que el email exista y la contraseña sea correcta
     * Si es válido, genera y retorna un token de autenticación
     * Si no es válido, lanza una excepción de validación
     * 
     * @param Request $request Contiene email y password
     * @return \Illuminate\Http\JsonResponse Usuario autenticado y token de acceso
     */
    public function login(Request $request)
    {
        // Validar datos de entrada
        // - email: requerido y debe ser un email válido
        // - password: requerido
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        // Buscar el usuario por email en la base de datos
        $user = User::where('email', $validated['email'])->first();

        // Verificar que el usuario exista y la contraseña sea correcta
        // Hash::check() compara la contraseña enviada con el hash almacenado
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            // Lanzar excepción si las credenciales son inválidas
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        // Retornar respuesta con usuario y token de acceso
        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken,
            'role' => $user->role,
        ]);
    }

    /**
     * Cierra la sesión del usuario actual
     * 
     * Elimina el token de acceso actual para invalidar la sesión
     * El usuario deberá autenticarse nuevamente para obtener un nuevo token
     * 
     * @param Request $request Solicitud con usuario autenticado
     * @return \Illuminate\Http\JsonResponse Mensaje de confirmación
     */
    public function logout(Request $request)
    {
        // Obtener el token actual del usuario autenticado y eliminarlo de la base de datos
        // Esto invalida el token y obliga al usuario a autenticarse nuevamente
        $request->user()->currentAccessToken()->delete();

        // Retornar confirmación del logout
        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Devuelve el perfil del usuario autenticado.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        return response()->json($request->user()->fresh());
    }

    /**
     * Actualiza el perfil del usuario autenticado, incluyendo avatar.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'avatar' => 'sometimes|file|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_avatar' => 'sometimes|boolean',
        ]);

        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }

        if (array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
        }

        $user->save();

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Actualiza únicamente el avatar del usuario autenticado.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAvatar(Request $request)
    {
        return $this->updateProfile($request);
    }

    /**
     * Elimina el avatar del usuario autenticado.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAvatar(Request $request)
    {
        $request->merge(['remove_avatar' => true]);

        return $this->updateProfile($request);
    }

    /**
     * Verifica que el email esté registrado en la base de datos
     * 
     * @param Request $request Contiene email
     * @return \Illuminate\Http\JsonResponse Mensaje de confirmación o error
     */
    public function forgotPassword(Request $request)
    {
        Log::info('Forgot password iniciado', ['request_data' => $request->all()]);
        
        // Validar que el email sea válido
        $validated = $request->validate([
            'email' => 'required|string|email',
        ]);

        // Buscar usuario por email
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'El email no está registrado en nuestra plataforma.',
            ], 404);
        }

        Log::info('Email verificado correctamente', ['email' => $validated['email']]);

        return response()->json([
            'message' => 'Email verificado correctamente. Puedes proceder a cambiar tu contraseña.',
            'email' => $validated['email'],
        ]);
    }

    /**
     * Cambia la contraseña del usuario
     * 
     * @param Request $request Contiene email, password y password_confirmation
     * @return \Illuminate\Http\JsonResponse Usuario actualizado y mensaje de confirmación
     */
    public function resetPassword(Request $request)
    {
        Log::info('Reset password iniciado', ['email' => $request->email]);
        
        // Validar datos de entrada
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Buscar usuario por email
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        // Actualizar contraseña
        $user->password = Hash::make($validated['password']);
        $user->save();

        Log::info('Contraseña actualizada correctamente', ['email' => $validated['email']]);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Obtiene la URL de redirección para autenticación con Google
     * 
     * @return \Illuminate\Http\JsonResponse URL de redirección de Google
     */
    public function googleRedirect()
    {
        // Construir la URL de autorización de Google
        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect_uri');
        $scopes = ['openid', 'profile', 'email'];

        $googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return response()->json([
            'google_auth_url' => $googleAuthUrl,
        ]);
    }

    /**
     * Maneja el callback de autenticación con Google
     * 
     * Intercambia el código de autorización por un token de acceso
     * Obtiene la información del usuario desde Google
     * Crea o actualiza el usuario en la base de datos según el tipo de operación
     * Genera y retorna un token de autenticación local
     * 
     * Comportamiento:
     * - type='login': Retorna error si usuario NO existe
     * - type='register': Retorna error si usuario EXISTE, crea nuevo usuario
     * 
     * @param Request $request Contiene 'code' y 'type' (login|register)
     * @return \Illuminate\Http\JsonResponse Usuario autenticado y token de acceso
     */
    public function googleCallback(Request $request)
    {
        // Validar que se recibió el código y el tipo de operación
        $validated = $request->validate([
            'code' => 'required|string',
            'type' => 'required|string|in:login,register', // 'login' o 'register'
        ]);

        try {
            Log::info('GoogleCallback iniciado', ['type' => $validated['type'], 'code_length' => strlen($validated['code'])]);
            
            // Intercambiar código por token de acceso
            $tokenData = $this->exchangeGoogleCode($validated['code']);
            Log::info('Token obtenido de Google', ['has_access_token' => isset($tokenData['access_token'])]);

            // Obtener información del usuario desde Google
            $googleUser = $this->getGoogleUserInfo($tokenData['access_token']);
            Log::info('Información de usuario de Google obtenida', ['email' => $googleUser['email'] ?? 'N/A', 'name' => $googleUser['name'] ?? 'N/A']);

            // Buscar usuario existente por email
            $existingUser = User::where('email', $googleUser['email'])->first();
            Log::info('Usuario existente buscado', ['found' => $existingUser !== null, 'type' => $validated['type']]);

            // Manejo según el tipo de operación
            if ($validated['type'] === 'login') {
                // Google LOGIN: usuario DEBE existir
                if (!$existingUser) {
                    return response()->json([
                        'message' => 'User not found. Please register with Google first.',
                        'error' => 'user_not_found',
                    ], 401);
                }

                // Actualizar tokens de Google del usuario existente
                $existingUser->update([
                    'google_id' => $googleUser['id'],
                    'google_token' => $tokenData['access_token'],
                    'google_refresh_token' => $tokenData['refresh_token'] ?? $existingUser->google_refresh_token,
                    'google_token_expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
                ]);

                $user = $existingUser;
            } else {
                // Google REGISTER: usuario NO debe existir
                if ($existingUser) {
                    return response()->json([
                        'message' => 'User already registered. Please log in instead.',
                        'error' => 'user_exists',
                    ], 409); // 409 Conflict
                }

                // Crear nuevo usuario
                $user = User::create([
                    'name' => $googleUser['name'],
                    'email' => $googleUser['email'],
                    'google_id' => $googleUser['id'],
                    'google_token' => $tokenData['access_token'],
                    'google_refresh_token' => $tokenData['refresh_token'] ?? null,
                    'google_token_expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
                ]);
                Log::info('Nuevo usuario creado con Google', ['user_id' => $user->id, 'email' => $user->email]);
            }

            // Generar token local para el usuario
            $token = $user->createToken('auth_token')->plainTextToken;
            Log::info('Token local generado exitosamente', ['user_id' => $user->id]);

            return response()->json([
                'message' => 'Google authentication successful',
                'user' => $user,
                'token' => $token,
                'role' => $user->role,
                'isNewUser' => $validated['type'] === 'register', // Indicar si fue nuevo usuario
            ]);
        } catch (\Exception $e) {
            Log::error('Error en GoogleCallback', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Google authentication failed',
                'error' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Intercambia el código de autorización por un token de acceso
     * 
     * @param string $code Código de autorización de Google
     * @return array Array con access_token, refresh_token, expires_in
     */
    private function exchangeGoogleCode(string $code): array
    {
        $ch = curl_init();

        $postFields = http_build_query([
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.google.redirect_uri'),
        ]);

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://oauth2.googleapis.com/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_SSL_VERIFYPEER => false,  // Desabilitar verificación SSL para desarrollo local
            CURLOPT_SSL_VERIFYHOST => 0,      // Desabilitar verificación de hostname
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL Error: ' . $error);
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new \Exception('Google OAuth Error: ' . ($data['error_description'] ?? $data['error']) . ' (HTTP ' . $httpCode . ')');
        }

        if (!isset($data['access_token'])) {
            throw new \Exception('Invalid Google response: ' . json_encode($data));
        }

        return $data;
    }

    /**
     * Obtiene la información del usuario desde Google
     * 
     * @param string $accessToken Token de acceso de Google
     * @return array Array con id, name, email
     */
    private function getGoogleUserInfo(string $accessToken): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://www.googleapis.com/oauth2/v2/userinfo',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_SSL_VERIFYPEER => false,  // Desabilitar verificación SSL para desarrollo local
            CURLOPT_SSL_VERIFYHOST => 0,      // Desabilitar verificación de hostname
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL Error: ' . $error);
        }

        $data = json_decode($response, true);

        if (!isset($data['id'])) {
            throw new \Exception('Failed to retrieve Google user information');
        }

        return [
            'id' => $data['id'],
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? '',
        ];
    }
}
