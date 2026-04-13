<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'google_id',
        'google_token',
        'google_refresh_token',
        'google_token_expires_at',
        'teacher_status',
        'subject',
        'bio',
        'price_per_hour',
        'avatar_path',
    ];

    /**
     * Atributos calculados que se incluirán en JSON.
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'google_token_expires_at' => 'datetime',
            'price_per_hour' => 'decimal:2',
        ];
    }

    /**
     * Relación con las solicitudes de profesor
     */
    public function teacherRequests()
    {
        return $this->hasMany(TeacherRequest::class);
    }

    /**
     * Obtener la última solicitud de profesor
     */
    public function latestTeacherRequest()
    {
        return $this->hasOne(TeacherRequest::class)->latestOfMany();
    }

    /**
     * Relación con las reseñas que el usuario recibe como profesor
     */
    public function ratings()
    {
        return $this->hasMany(Rating::class, 'teacher_id');
    }

    /**
     * Relación con las reseñas que el usuario ha dado como estudiante
     */
    public function givenRatings()
    {
        return $this->hasMany(Rating::class, 'student_id');
    }

    /**
     * Verifica si el usuario es administrador
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Verifica si el usuario es profesor
     */
    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    /**
     * Verifica si el usuario es usuario normal
     */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * URL pública del avatar del usuario.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) {
            return null;
        }

        return asset('storage/'.$this->avatar_path);
    }

    /**
     * Relación muchos a muchos con intereses
     */
    public function interests()
    {
        return $this->belongsToMany(Interest::class, 'interest_user');
    }
}
