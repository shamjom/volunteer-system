<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    protected $fillable = [
        'event_id',
        'volunteer_id',
        'registration_status',
        'attendance_status',
        'volunteer_hours',
        'registered_at',
        'cancellation_reason',
        'cancelled_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'cancelled_at'  => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function volunteer()
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }
}