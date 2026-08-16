<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolunteerProfile extends Model
{
   protected $fillable = [
    'user_id',
    'phone',
    'address',
    'city',
    'emergency_contact',
    'cv_status',
    'avatar',
    'interests',
    'preferred_hours_per_week',
    'notifications_enabled',
    'language',
    'total_hours',
    'status',];


   protected $casts = [
    'interests' => 'array',
    'notifications_enabled' => 'boolean',];

    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}