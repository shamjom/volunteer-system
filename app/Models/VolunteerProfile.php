<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolunteerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'address',
        'total_hours',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}