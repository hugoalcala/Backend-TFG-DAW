<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
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
        // Validar datos de entrada
        // - name: requerido, string, máximo 255 caracteres
        // - email: requerido, email válido, único en la tabla users
        // - password: requerido, mínimo 8 caracteres, debe ser confirmado
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

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
        ], 201); // Status 201: Created
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
}
