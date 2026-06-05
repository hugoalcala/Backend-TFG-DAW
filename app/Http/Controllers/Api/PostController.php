<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostLike;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    /**
     * Determinar el tipo de archivo según la extensión
     */
    private function deletePostFile(string $path): void
    {
        if (str_starts_with($path, 'http')) {
            $publicId = CloudinaryService::extractPublicId($path);
            if ($publicId) {
                (new CloudinaryService())->delete($publicId, 'auto');
            }
        } else {
            Storage::disk('public')->delete($path);
        }
    }

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

                // Subir archivo
                if (env('CLOUDINARY_CLOUD_NAME')) {
                    $result = (new CloudinaryService())->upload($file, 'posts');
                    $filePath = $result['url'];
                } else {
                    $storedName = time() . '_' . uniqid() . '.' . $extension;
                    $filePath = $file->storeAs('posts', $storedName, 'public');
                }
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
                    $this->deletePostFile($post->file_path);
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

                $cloudinary = new CloudinaryService();
                $result = $cloudinary->upload($file, 'posts');
                $post->file_path = $result['url'];
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
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado',
                ], 401);
            }
            
            $post = Post::findOrFail($id);

            // Verificar que es el dueño del post
            if ($post->user_id !== $user->id) {
                Log::warning("Usuario {$user->id} intentó eliminar post {$id} del usuario {$post->user_id}");
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar este post',
                ], 403);
            }

            // Eliminar archivo si existe
            if ($post->file_path) {
                $this->deletePostFile($post->file_path);
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
            Log::error('Error al eliminar post: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el post: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dar like a un post
     * 
     * POST /api/posts/{id}/like
     */
    public function like(Request $request, $id)
    {
        try {
            $user = $request->user();
            $post = Post::findOrFail($id);

            // Verificar si el usuario ya dio like
            $existingLike = PostLike::where('user_id', $user->id)
                ->where('post_id', $id)
                ->first();

            if ($existingLike) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya le has dado like a este post',
                ], 422);
            }

            // Crear el like
            PostLike::create([
                'user_id' => $user->id,
                'post_id' => $id,
            ]);

            // Incrementar likes_count
            $post->likes_count = ($post->likes_count ?? 0) + 1;
            $post->save();

            return response()->json([
                'success' => true,
                'message' => 'Like agregado',
                'likes_count' => $post->likes_count,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Post no encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al dar like: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al dar like',
            ], 500);
        }
    }

    /**
     * Quitar like de un post
     * 
     * POST /api/posts/{id}/unlike
     */
    public function unlike(Request $request, $id)
    {
        try {
            $user = $request->user();
            $post = Post::findOrFail($id);

            // Buscar y eliminar el like
            $like = PostLike::where('user_id', $user->id)
                ->where('post_id', $id)
                ->first();

            if (!$like) {
                return response()->json([
                    'success' => false,
                    'message' => 'No has dado like a este post',
                ], 422);
            }

            $like->delete();

            // Decrementar likes_count
            $post->likes_count = max(0, ($post->likes_count ?? 1) - 1);
            $post->save();

            return response()->json([
                'success' => true,
                'message' => 'Like removido',
                'likes_count' => $post->likes_count,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Post no encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al remover like: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al remover like',
            ], 500);
        }
    }

    /**
     * Obtener IDs de posts que el usuario ya le dio like
     * 
     * GET /api/user/liked-posts
     */
    public function getUserLikedPosts(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                    'data' => []
                ], 401);
            }
            
            $likedPostIds = PostLike::where('user_id', $user->id)
                ->pluck('post_id')
                ->map(fn($id) => (int)$id)
                ->toArray();
            
            Log::info("User {$user->id} liked posts: " . json_encode($likedPostIds));
            
            return response()->json([
                'success' => true,
                'data' => $likedPostIds,
                'count' => count($likedPostIds),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching user liked posts: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener likes',
                'data' => []
            ], 500);
        }
    }

    /**
     * Obtener comentarios de un post con replies
     * 
     * GET /api/posts/{id}/comments
     */
    public function getComments(Request $request, $id)
    {
        try {
            $post = Post::findOrFail($id);
            
            // Obtener solo comentarios principales (sin parent)
            $comments = $post->comments()
                ->with(['user', 'replies' => function($query) {
                    $query->with('user')->orderBy('created_at', 'asc');
                }])
                ->whereNull('parent_comment_id')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($comment) {
                    return $this->formatCommentData($comment);
                });
            
            return response()->json([
                'success' => true,
                'data' => $comments,
                'count' => $comments->count(),
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Post no encontrado',
                'data' => []
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching comments: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener comentarios',
                'data' => []
            ], 500);
        }
    }

    /**
     * Formatear datos de comentario con replies
     */
    private function formatCommentData($comment)
    {
        return [
            'id' => $comment->id,
            'post_id' => $comment->post_id,
            'user_id' => $comment->user_id,
            'parent_comment_id' => $comment->parent_comment_id,
            'comment' => $comment->comment,
            'author' => $comment->user->name,
            'avatar' => $comment->user->avatar_path ? '👤' : '👤',
            'avatar_url' => $comment->user->avatar_url,
            'timestamp' => $comment->created_at->diffForHumans(),
            'created_at' => $comment->created_at,
            'replies' => $comment->replies ? $comment->replies->map(fn($reply) => $this->formatCommentData($reply))->toArray() : [],
        ];
    }

    /**
     * Crear un comentario en un post (o respuesta a un comentario)
     * 
     * POST /api/posts/{id}/comments
     */
    public function addComment(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $post = Post::findOrFail($id);
            
            // Validar comentario
            $request->validate([
                'comment' => 'required|string|min:1|max:1000',
                'parent_comment_id' => 'nullable|integer|exists:post_comments,id',
            ]);
            
            // Crear comentario
            $comment = $post->comments()->create([
                'user_id' => $user->id,
                'comment' => $request->comment,
                'parent_comment_id' => $request->parent_comment_id ?? null,
            ]);
            
            // Incrementar contador de comentarios del post (solo si es comentario principal)
            if (!$request->parent_comment_id) {
                $post->comments_count = ($post->comments_count ?? 0) + 1;
                $post->save();
            }
            
            // Cargar la relación del usuario
            $comment->load('user');
            
            return response()->json([
                'success' => true,
                'message' => 'Comentario añadido',
                'data' => $this->formatCommentData($comment)
            ], 201);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Post no encontrado',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error adding comment: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al añadir comentario',
            ], 500);
        }
    }

    /**
     * Actualizar un comentario en un post
     * 
     * PUT /api/posts/{postId}/comments/{commentId}
     */
    public function updateComment(Request $request, $postId, $commentId)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $post = Post::findOrFail($postId);
            $comment = $post->comments()->findOrFail($commentId);
            
            // Verificar que el comentario es del usuario actual
            if ($comment->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes editar este comentario',
                ], 403);
            }
            
            // Validar comentario
            $request->validate([
                'comment' => 'required|string|min:1|max:1000',
            ]);
            
            // Actualizar comentario
            $comment->update([
                'comment' => $request->comment,
            ]);
            
            // Cargar la relación del usuario
            $comment->load('user');
            
            return response()->json([
                'success' => true,
                'message' => 'Comentario actualizado',
                'data' => $this->formatCommentData($comment)
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Comentario o post no encontrado',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating comment: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar comentario',
            ], 500);
        }
    }

    /**
     * Eliminar un comentario en un post
     * 
     * DELETE /api/posts/{postId}/comments/{commentId}
     */
    public function deleteComment(Request $request, $postId, $commentId)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $post = Post::findOrFail($postId);
            $comment = $post->comments()->findOrFail($commentId);
            
            // Verificar que el comentario es del usuario actual o es admin
            if ($comment->user_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes eliminar este comentario',
                ], 403);
            }
            
            // Eliminar comentario
            $comment->delete();
            
            // Decrementar contador de comentarios del post
            $post->comments_count = max(0, ($post->comments_count ?? 1) - 1);
            $post->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Comentario eliminado',
                'comments_count' => $post->comments_count,
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Comentario o post no encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting comment: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar comentario',
            ], 500);
        }
    }

}

