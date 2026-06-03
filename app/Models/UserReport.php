<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'reported_user_id',
        'reported_by_user_id',
        'post_id',
        'report_type',
        'reason',
        'details',
        'status',
        'admin_notes',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el usuario reportado
     */
    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    /**
     * Relación con el usuario que reportó
     */
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /**
     * Relación con el admin que revisó
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * Relación con el post denunciado (solo para report_type = 'post')
     */
    public function post()
    {
        return $this->belongsTo(\App\Models\Post::class);
    }
}
