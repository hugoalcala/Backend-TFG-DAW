<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostComment extends Model
{
    protected $guarded = [];
    protected $table = 'post_comments';

    /**
     * Relación: Un comentario pertenece a un post
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Relación: Un comentario pertenece a un usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: Un comentario puede ser respuesta a otro comentario
     */
    public function parentComment()
    {
        return $this->belongsTo(PostComment::class, 'parent_comment_id');
    }

    /**
     * Relación: Un comentario puede tener respuestas
     */
    public function replies()
    {
        return $this->hasMany(PostComment::class, 'parent_comment_id');
    }
}
