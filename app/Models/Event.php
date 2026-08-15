<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\EventRegistration;

class Event extends Model
{
    protected $fillable = [
        'title',
        'description',
        'location',
        'start_date',
        'end_date',
        'max_volunteers',
        'status',
        'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks()
    {
    return $this->hasMany(Task::class);
    }

    public function registrations()
   {
    return $this->hasMany(EventRegistration::class);
   }
}