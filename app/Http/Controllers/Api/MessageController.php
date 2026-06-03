<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    /**
     * Obtener todas las conversaciones del usuario autenticado
     */
    public function getConversations()
    {
        $currentUser = Auth::user();

        // Obtener todas las conversaciones donde el usuario es participante
        $conversations = Conversation::where(function ($query) use ($currentUser) {
            $query->where('user_id', $currentUser->id)
                  ->orWhere('recipient_id', $currentUser->id);
        })
        ->with(['user:id,name,avatar_path,role', 'recipient:id,name,avatar_path,role', 'messages' => function($q) {
            $q->with('fromUser:id,name,role');
            $q->select('id', 'conversation_id', 'from_user_id', 'to_user_id', 'message', 'body', 'created_at', 'read_at');
        }])
        ->orderByDesc('last_message_at')
        ->get()
        ->map(function ($conversation) use ($currentUser) {
            try {
                // Obtener el otro usuario (el que no es el actual)
                $otherUser = $conversation->getOtherUser($currentUser->id);
                
                if (!$otherUser) {
                    \Log::warning('Other user not found for conversation', [
                        'conversation_id' => $conversation->id,
                        'current_user_id' => $currentUser->id,
                        'user_id' => $conversation->user_id,
                        'recipient_id' => $conversation->recipient_id,
                    ]);
                    return null;
                }
                
                // Obtener el último mensaje
                $lastMessage = $conversation->messages()->latest()->first();

                return [
                    'id' => $conversation->id,
                    'user_id' => $otherUser->id,
                    'recipient_id' => $otherUser->id,
                    'name' => $otherUser->role === 'admin' ? 'Administración' : ($otherUser->name ?? 'Usuario'),
                    'avatar' => $otherUser->avatar_path ? asset('storage/' . $otherUser->avatar_path) : '👤',
                    'lastMessage' => $lastMessage?->message ?? $lastMessage?->body ?? '',
                    'timestamp' => $lastMessage?->created_at?->format('H:i') ?? '',
                    'unread' => $conversation->messages()
                        ->where('to_user_id', $currentUser->id)
                        ->whereNull('read_at')
                        ->count(),
                    'messages' => $conversation->messages->map(function ($msg) use ($currentUser) {
                        if ($msg->from_user_id === $currentUser->id) {
                            $fromUserName = 'Tú';
                        } elseif ($msg->fromUser && $msg->fromUser->role === 'admin') {
                            $fromUserName = 'Administración';
                        } else {
                            $fromUserName = 'Usuario';
                        }
                        
                        return [
                            'id' => $msg->id,
                            'sender' => $fromUserName,
                            'text' => $msg->message ?? $msg->body ?? '',
                            'time' => $msg->created_at->format('H:i'),
                            'read_at' => $msg->read_at,
                        ];
                    })->toArray(),
                ];
            } catch (\Exception $e) {
                \Log::error('Error mapping conversation', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        })
        ->filter() // Eliminar nulls
        ->values(); // Reindexar

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    /**
     * Obtener una conversación específica
     */
    public function getConversation($conversationId)
    {
        $currentUser = Auth::user();
        $conversation = Conversation::with(['user:id,name,avatar_path,role', 'recipient:id,name,avatar_path,role', 'messages' => function($q) {
            $q->with('fromUser:id,name,role');
            $q->select('id', 'conversation_id', 'from_user_id', 'to_user_id', 'message', 'body', 'created_at', 'read_at');
        }])->find($conversationId);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversación no encontrada',
            ], 404);
        }

        // Verificar que el usuario sea parte de la conversación
        if ($conversation->user_id !== $currentUser->id && $conversation->recipient_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a esta conversación',
            ], 403);
        }

        $otherUser = $conversation->getOtherUser($currentUser->id);
        
        if (!$otherUser) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo encontrar el otro usuario',
            ], 500);
        }

        $messages = $conversation->messages;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $conversation->id,
                'user_id' => $otherUser->id,
                'recipient_id' => $otherUser->id,
                'name' => $otherUser->role === 'admin' ? 'Administración' : ($otherUser->name ?? 'Usuario'),
                'avatar' => $otherUser->avatar_path ? asset('storage/' . $otherUser->avatar_path) : '👤',
                'messages' => $messages->map(function ($msg) use ($currentUser) {
                    if ($msg->from_user_id === $currentUser->id) {
                        $fromUserName = 'Tú';
                    } elseif ($msg->fromUser && $msg->fromUser->role === 'admin') {
                        $fromUserName = 'Administración';
                    } else {
                        $fromUserName = 'Usuario';
                    }
                    
                    return [
                        'id' => $msg->id,
                        'sender' => $fromUserName,
                        'text' => $msg->message ?? $msg->body ?? '',
                        'time' => $msg->created_at->format('H:i'),
                        'read_at' => $msg->read_at,
                    ];
                })->toArray(),
            ],
        ]);
    }

    /**
     * Crear una nueva conversación
     */
    public function createConversation(Request $request)
    {
        $currentUser = Auth::user();
        $recipientId = $request->input('user_id');

        \Log::info('Creating conversation', [
            'current_user_id' => $currentUser->id,
            'recipient_id' => $recipientId,
        ]);

        // Validar que el usuario destinatario existe
        $recipient = User::select(['id', 'name', 'avatar_path'])->find($recipientId);
        if (!$recipient) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        // No permitir conversación consigo mismo
        if ($recipientId === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes iniciar una conversación contigo mismo',
            ], 422);
        }

        // Obtener o crear la conversación
        $conversation = Conversation::betweenUsers($currentUser->id, $recipientId);
        $conversation->load(['messages:id,conversation_id,from_user_id,to_user_id,message,body,created_at,read_at']);

        \Log::info('Conversation created/found', [
            'conversation_id' => $conversation->id,
            'messages_count' => $conversation->messages->count(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $conversation->id,
                'user_id' => $recipient->id,
                'recipient_id' => $recipient->id,
                'name' => $recipient->name ?? 'Usuario',
                'avatar' => $recipient->avatar_path ? asset('storage/' . $recipient->avatar_path) : '👤',
                'messages' => $conversation->messages->map(function ($msg) use ($currentUser) {
                    return [
                        'id' => $msg->id,
                        'sender' => $msg->from_user_id === $currentUser->id ? 'Tú' : 'Usuario',
                        'text' => $msg->message ?? $msg->body ?? '',
                        'time' => $msg->created_at->format('H:i'),
                        'read_at' => $msg->read_at,
                    ];
                })->toArray(),
                'lastMessage' => '',
                'timestamp' => '',
                'unread' => 0,
            ],
        ]);
    }

    /**
     * Enviar un mensaje
     */
    public function sendMessage(Request $request)
    {
        $currentUser = Auth::user();
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|string|max:5000',
        ]);

        $conversation = Conversation::find($request->input('conversation_id'));

        // Verificar que el usuario sea parte de la conversación
        if ($conversation->user_id !== $currentUser->id && $conversation->recipient_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a esta conversación',
            ], 403);
        }

        // Determinar al usuario destinatario
        $recipientId = $conversation->user_id === $currentUser->id 
            ? $conversation->recipient_id 
            : $conversation->user_id;

        // Crear el mensaje
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'from_user_id' => $currentUser->id,
            'to_user_id' => $recipientId,
            'message' => $request->input('message'),
            'body' => $request->input('message'), // Mantener compatibilidad con schema anterior
            'subject' => 'Chat Message', // Mantener compatibilidad
        ]);

        // Actualizar last_message_at de la conversación
        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'sender' => 'Tú',
                'text' => $message->message,
                'time' => $message->created_at->format('H:i'),
            ],
        ]);
    }

    /**
     * Marcar una conversación como leída
     */
    public function markAsRead($conversationId)
    {
        $currentUser = Auth::user();
        $conversation = Conversation::find($conversationId);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversación no encontrada',
            ], 404);
        }

        // Verificar que el usuario sea parte de la conversación
        if ($conversation->user_id !== $currentUser->id && $conversation->recipient_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a esta conversación',
            ], 403);
        }

        // Marcar todos los mensajes recibidos como leídos
        Message::where('conversation_id', $conversationId)
            ->where('to_user_id', $currentUser->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Mensajes marcados como leídos',
        ]);
    }

    /**
     * Enviar aviso de admin a usuarios específicos o a todos
     * Solo los admins pueden usar este endpoint
     */
    public function sendAdminNotice(Request $request)
    {
        $currentUser = Auth::user();

        // Verificar que sea admin
        if ($currentUser->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para enviar avisos',
            ], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'recipient_ids' => 'nullable|array', // Si es null, envía a todos
            'recipient_ids.*' => 'integer|exists:users,id',
        ]);

        // Usar el admin actual como cuenta de envío
        $adminAccount = $currentUser;

        // Determinar los destinatarios
        if ($request->has('recipient_ids') && is_array($request->input('recipient_ids'))) {
            // Enviar a usuarios específicos
            $recipientIds = $request->input('recipient_ids');
        } else {
            // Enviar a todos los usuarios excepto al admin
            $recipientIds = User::where('id', '!=', $adminAccount->id)
                ->pluck('id')
                ->toArray();
        }

        $createdMessages = [];

        foreach ($recipientIds as $recipientId) {
            // Verificar que no sea el mismo admin
            if ($recipientId === $adminAccount->id) {
                continue;
            }

            // Obtener o crear conversación entre admin y usuario
            $conversation = Conversation::betweenUsers($adminAccount->id, $recipientId);

            // Crear mensaje
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'from_user_id' => $adminAccount->id,
                'to_user_id' => $recipientId,
                'message' => $request->input('message'),
                'body' => $request->input('message'),
                'subject' => $request->input('title'),
            ]);

            // Actualizar timestamp de la conversación
            $conversation->update(['last_message_at' => now()]);

            $createdMessages[] = [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'recipient_id' => $recipientId,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Aviso enviado exitosamente',
            'messages_sent' => count($createdMessages),
            'data' => $createdMessages,
        ]);
    }

    /**
     * Obtener conversaciones con notificaciones de admin
     * Los usuarios ven los mensajes de admin en una conversación especial
     */
    public function getAdminNotices()
    {
        $currentUser = Auth::user();

        // Obtener el primer admin del sistema
        $adminAccount = User::where('role', 'admin')->first();

        if (!$adminAccount) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        // Obtener conversación del usuario con admin
        $conversation = Conversation::where(function ($query) use ($currentUser, $adminAccount) {
            $query->where('user_id', $currentUser->id)->where('recipient_id', $adminAccount->id)
                  ->orWhere('user_id', $adminAccount->id)->where('recipient_id', $currentUser->id);
        })->first();

        if (!$conversation) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        // Obtener los mensajes de admin
        $messages = $conversation->messages()
            ->where('from_user_id', $adminAccount->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'title' => $msg->subject,
                    'message' => $msg->message ?? $msg->body,
                    'created_at' => $msg->created_at->format('Y-m-d H:i'),
                    'read' => $msg->read_at !== null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    /**
     * Reportar un usuario
     */
    public function reportUser(Request $request)
    {
        $currentUser = Auth::user();

        $request->validate([
            'reported_user_id' => 'required|integer|exists:users,id',
            'reason' => 'required|string|max:255',
            'details' => 'nullable|string|max:5000',
        ]);

        // Verificar que no se reporte a si mismo
        if ($request->input('reported_user_id') === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes reportarte a ti mismo',
            ], 400);
        }

        // Verificar que no exista un reporte previo del mismo usuario
        $existingReport = UserReport::where('reported_user_id', $request->input('reported_user_id'))
            ->where('reported_by_user_id', $currentUser->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingReport) {
            return response()->json([
                'success' => false,
                'message' => 'Ya has reportado a este usuario anteriormente',
            ], 400);
        }

        $report = UserReport::create([
            'reported_user_id' => $request->input('reported_user_id'),
            'reported_by_user_id' => $currentUser->id,
            'reason' => $request->input('reason'),
            'details' => $request->input('details'),
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Denuncia enviada a administración',
            'data' => $report,
        ]);
    }

    /**
     * Denunciar un post
     */
    public function reportPost(Request $request, $postId)
    {
        $currentUser = Auth::user();

        $request->validate([
            'reason'  => 'required|string|max:255',
            'details' => 'nullable|string|max:5000',
        ]);

        $post = Post::findOrFail($postId);

        if ($post->user_id === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes denunciar tu propio post',
            ], 400);
        }

        $existingReport = UserReport::where('post_id', $postId)
            ->where('reported_by_user_id', $currentUser->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingReport) {
            return response()->json([
                'success' => false,
                'message' => 'Ya has denunciado este post anteriormente',
            ], 400);
        }

        UserReport::create([
            'reported_user_id'    => $post->user_id,
            'reported_by_user_id' => $currentUser->id,
            'post_id'             => $post->id,
            'report_type'         => 'post',
            'reason'              => $request->input('reason'),
            'details'             => $request->input('details'),
            'status'              => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Denuncia enviada a administración',
        ]);
    }

    /**
     * Eliminar una conversación
     */
    public function deleteConversation($conversationId)
    {
        $currentUser = Auth::user();

        $conversation = Conversation::find($conversationId);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversación no encontrada',
            ], 404);
        }

        // Verificar que el usuario sea parte de la conversación
        if ($conversation->user_id !== $currentUser->id && $conversation->recipient_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a esta conversación',
            ], 403);
        }

        // Eliminar la conversación y sus mensajes
        $conversation->messages()->delete();
        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversación eliminada',
        ]);
    }

    /**
     * Obtener todas las denuncias (solo admins)
     */
    public function getAllReports(Request $request)
    {
        $currentUser = Auth::user();

        // Verificar que sea admin
        if ($currentUser->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para acceder a denuncias',
            ], 403);
        }

        $query = UserReport::with([
            'reportedUser:id,name,email,avatar_path,role',
            'reporter:id,name,email',
            'post:id,content,user_id',
        ]);

        // Filtrar por estado
        if ($request->has('status') && $request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filtrar por razón
        if ($request->has('reason') && $request->input('reason')) {
            $query->where('reason', $request->input('reason'));
        }

        // Ordenar por más reciente primero
        $reports = $query->orderByDesc('created_at')->paginate($request->input('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $reports->items(),
            'total' => $reports->total(),
            'per_page' => $reports->perPage(),
            'current_page' => $reports->currentPage(),
            'last_page' => $reports->lastPage(),
        ]);
    }

    /**
     * Obtener detalles de una denuncia (solo admins)
     */
    public function getReportDetails($reportId)
    {
        $currentUser = Auth::user();

        // Verificar que sea admin
        if ($currentUser->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para acceder a denuncias',
            ], 403);
        }

        $report = UserReport::with([
            'reportedUser:id,name,email,avatar_path,role,created_at',
            'reporter:id,name,email',
            'reviewer:id,name,email',
            'post:id,content,user_id',
        ])->find($reportId);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Denuncia no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Aprobar una denuncia (solo admins)
     */
    public function approveReport(Request $request, $reportId)
    {
        $currentUser = Auth::user();

        // Verificar que sea admin
        if ($currentUser->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para aprobar denuncias',
            ], 403);
        }

        $report = UserReport::find($reportId);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Denuncia no encontrada',
            ], 404);
        }

        if ($report->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden aprobar denuncias pendientes',
            ], 400);
        }

        // Si es denuncia de post, eliminar el post
        if ($report->report_type === 'post' && $report->post_id) {
            Post::where('id', $report->post_id)->delete();
            // Marcar todas las denuncias de ese post como aprobadas
            UserReport::where('post_id', $report->post_id)
                ->where('status', 'pending')
                ->update([
                    'status'               => 'approved',
                    'reviewed_by_user_id'  => $currentUser->id,
                    'reviewed_at'          => now(),
                ]);
        } else {
            $report->update([
                'status'               => 'approved',
                'admin_notes'          => $request->input('admin_notes'),
                'reviewed_by_user_id'  => $currentUser->id,
                'reviewed_at'          => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Denuncia aprobada exitosamente',
            'data'    => $report,
        ]);
    }

    /**
     * Rechazar una denuncia (solo admins)
     */
    public function rejectReport(Request $request, $reportId)
    {
        $currentUser = Auth::user();

        // Verificar que sea admin
        if ($currentUser->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para rechazar denuncias',
            ], 403);
        }

        $report = UserReport::find($reportId);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Denuncia no encontrada',
            ], 404);
        }

        if ($report->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden rechazar denuncias pendientes',
            ], 400);
        }

        // Actualizar el reporte
        $report->update([
            'status' => 'rejected',
            'admin_notes' => $request->input('admin_notes'),
            'reviewed_by_user_id' => $currentUser->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Denuncia rechazada exitosamente',
            'data' => $report,
        ]);
    }
}


