<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'content',
        'file_path',
        'file_type',
        'file_name',
        'likes_count',
        'comments_count',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el usuario que creó el post
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con los likes del post
     */
    public function likes()
    {
        return $this->hasMany(PostLike::class);
    }

    /**
     * Relación con los comentarios del post
     */
    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    /**
     * Obtener la URL completa del archivo del post
     */
    public function getFileUrlAttribute()
    {
        if ($this->file_path) {
            return asset('storage/' . $this->file_path);
        }
        return null;
    }

    /**
     * Alias para compatibilidad con código anterior
     */
    public function getImageUrlAttribute()
    {
        return $this->file_url;
    }

    /**
     * Scope para obtener posts ordenados por fecha más reciente
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
