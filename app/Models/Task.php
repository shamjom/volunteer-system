<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{

    protected $fillable = [
        'event_id',
        'title',
        'description',
        'status',
        'last_status_note',
        'deadline',
        'required_volunteers',

    ];

    protected $casts = [
        'deadline' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function volunteers()
    {
        return $this->belongsToMany(User::class);
    }
}