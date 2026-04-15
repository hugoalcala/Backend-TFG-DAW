<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    /**
     * Determinar el tipo de archivo según la extensión
     */
    private function getFileType(string $mimeType, string $extension): string
    {
        // Tipos de imagen
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            return 'image';
        }
        
        // Tipos de video
        if (in_array($extension, ['mp4', 'webm', 'avi', 'mov', 'mkv', 'flv'])) {
            return 'video';
        }
        
        // Tipos de documento
        if ($extension === 'pdf' || in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])) {
            return 'document';
        }
        
        // Tipo genérico
        if (strpos($mimeType, 'text') !== false) {
            return 'text';
        }
        
        return 'file';
    }

    /**
     * Obtener todos los posts (feed)
     * 
     * GET /api/posts
     */
    public function index()
    {
        try {
            $posts = Post::with('user:id,name,avatar_path')
                ->latest()
                ->get()
                ->map(function ($post) {
                    return [
                        'id' => $post->id,
                        'user_id' => $post->user_id,
                        'content' => $post->content,
                        'file_path' => $post->file_path,
                        'file_url' => $post->file_url,
                        'file_type' => $post->file_type,
                        'file_name' => $post->file_name,
                        'likes_count' => $post->likes_count,
                        'comments_count' => $post->comments_count,
                        'author' => $post->user->name,
                        'avatar_url' => $post->user->avatar_url,
                        'created_at' => $post->created_at->toIso8601String(),
                        'updated_at' => $post->updated_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $posts,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener posts: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los posts',
            ], 500);
        }
    }

    /**
     * Crear un nuevo post
     * 
     * POST /api/posts
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            // Validar entrada
            $validated = $request->validate([
                'content' => 'required|string|max:5000',
                'file' => 'nullable|file|max:51200', // max 50MB
            ]);

            $filePath = null;
            $fileType = null;
            $fileName = null;

            // Procesar archivo si existe
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $originalName = $file->getClientOriginalName();
                $extension = strtolower($file->getClientOriginalExtension());
                $mimeType = $file->getMimeType();
                
                // Validar extensiones permitidas
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'webm', 'avi', 'mov', 'mkv', 'flv', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar'];
                
                if (!in_array($extension, $allowedExtensions)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tipo de archivo no permitido. Extensiones válidas: ' . implode(', ', $allowedExtensions),
                    ], 422);
                }

                // Generar nombre único y guardar
                $storedName = time() . '_' . uniqid() . '.' . $extension;
                $filePath = $file->storeAs('posts', $storedName, 'public');
                $fileType = $this->getFileType($mimeType, $extension);
                $fileName = $originalName;
            }

            // Crear el post
            $post = Post::create([
                'user_id' => $user->id,
                'content' => $validated['content'],
                'file_path' => $filePath,
                'file_type' => $fileType,
                'file_name' => $fileName,
            ]);

            // Cargar relación con usuario
            $post->load('user:id,name,avatar_path');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $post->id,
                    'user_id' => $post->user_id,
                    'content' => $post->content,
                    'file_path' => $post->file_path,
                    'file_url' => $post->file_url,
                    'file_type' => $post->file_type,
                    'file_name' => $post->file_name,
                    'likes_count' => $post->likes_count,
                    'comments_count' => $post->comments_count,
                    'author' => $post->user->name,
                    'avatar_url' => $post->user->avatar_url,
                    'created_at' => $post->created_at->toIso8601String(),
                ],
                'message' => 'Post creado exitosamente',
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear post: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el post',
            ], 500);
        }
    }

    /**
     * Actualizar un post
     * 
     * POST /api/posts/{id} (usando _method=PUT para compatibilidad con FormData)
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $post = Post::findOrFail($id);

            // Verificar que es el dueño del post
            if ($post->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para editar este post',
                ], 403);
            }

            // Validar entrada
            $validated = $request->validate([
                'content' => 'required|string|max:5000',
                'file' => 'nullable|file|max:51200',
            ]);

            // Actualizar contenido
            $post->content = $validated['content'];

            // Procesar nuevo archivo si existe
            if ($request->hasFile('file')) {
                // Eliminar archivo anterior
                if ($post->file_path) {
                    Storage::disk('public')->delete($post->file_path);
                }

                $file = $request->file('file');
                $originalName = $file->getClientOriginalName();
                $extension = strtolower($file->getClientOriginalExtension());
                $mimeType = $file->getMimeType();
                
                // Validar extensiones
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'webm', 'avi', 'mov', 'mkv', 'flv', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar'];
                
                if (!in_array($extension, $allowedExtensions)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tipo de archivo no permitido',
                    ], 422);
                }

                $storedName = time() . '_' . uniqid() . '.' . $extension;
                $filePath = $file->storeAs('posts', $storedName, 'public');
                $post->file_path = $filePath;
                $post->file_type = $this->getFileType($mimeType, $extension);
                $post->file_name = $originalName;
            }

            $post->save();
            $post->load('user:id,name,avatar_path');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $post->id,
                    'user_id' => $post->user_id,
                    'content' => $post->content,
                    'file_path' => $post->file_path,
                    'file_url' => $post->file_url,
                    'file_type' => $post->file_type,
                    'file_name' => $post->file_name,
                    'likes_count' => $post->likes_count,
                    'comments_count' => $post->comments_count,
                    'author' => $post->user->name,
                    'avatar_url' => $post->user->avatar_url,
                    'created_at' => $post->created_at->toIso8601String(),
                    'updated_at' => $post->updated_at->toIso8601String(),
                ],
                'message' => 'Post actualizado exitosamente',
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Post no encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al actualizar post: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el post',
            ], 500);
        }
    }

    /**
     * Eliminar un post
     * 
     * DELETE /api/posts/{id}
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $post = Post::findOrFail($id);

            // Verificar que es el dueño del post
            if ($post->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar este post',
                ], 403);
            }

            // Eliminar archivo si existe
            if ($post->file_path) {
                Storage::disk('public')->delete($post->file_path);
            }

            $post->delete();

            return response()->json([
                'success' => true,
                'message' => 'Post eliminado exitosamente',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Post no encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al eliminar post: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el post',
            ], 500);
        }
    }
}
