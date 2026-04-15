<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RatingReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'rating_id',
        'teacher_id',
        'reported_by_user_id',
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
     * Relación con la reseña reportada
     */
    public function rating()
    {
        return $this->belongsTo(Rating::class);
    }

    /**
     * Relación con el profesor
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Relación con el usuario que reportó
     */
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /**
     * Relación con el usuario admin que revisó
     */
    public function reviewedByUser()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * Obtener la relación completa con todos los datos necesarios
     */
    public function withAllRelations()
    {
        return $this->with([
            'rating' => function($q) {
                $q->select('id', 'teacher_id', 'student_id', 'rating', 'review', 'created_at')
                  ->with(['student' => function($sq) {
                      $sq->select('id', 'name', 'email', 'avatar_path');
                  }]);
            },
            'teacher' => function($q) {
                $q->select('id', 'name', 'email', 'subject');
            },
            'reporter' => function($q) {
                $q->select('id', 'name', 'email');
            },
        ]);
    }
}