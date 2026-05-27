<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Booking extends Model
{
    protected $fillable = [
        'student_id',
        'teacher_id',
        'hours',
        'price_per_hour',
        'total_amount',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'payment_status',
        'status',
        'cancellation_reason',
        'cancelled_at',
        'scheduled_start_date',
        'scheduled_end_date',
        'notes',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'scheduled_start_date' => 'datetime',
        'scheduled_end_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the student (user) who made the booking
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the teacher (user) being booked
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Check if booking is paid
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'completed';
    }

    /**
     * Check if booking is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if booking can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed', 'in_progress']);
    }
}
