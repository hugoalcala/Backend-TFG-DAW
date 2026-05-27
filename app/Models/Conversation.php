<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recipient_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * Obtener el usuario inicial de la conversación
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Obtener el usuario destinatario de la conversación
     */
    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Obtener todos los mensajes de la conversación
     */
    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Obtener el otro usuario de la conversación
     */
    public function getOtherUser($currentUserId)
    {
        return $this->user_id === $currentUserId ? $this->recipient : $this->user;
    }

    /**
     * Obtener la conversación entre dos usuarios (crear si no existe)
     */
    public static function betweenUsers($userId, $recipientId)
    {
        // Asegurarse de que user_id < recipient_id para consistencia
        if ($userId > $recipientId) {
            [$userId, $recipientId] = [$recipientId, $userId];
        }

        return self::firstOrCreate(
            [
                'user_id' => $userId,
                'recipient_id' => $recipientId,
            ]
        );
    }
}
