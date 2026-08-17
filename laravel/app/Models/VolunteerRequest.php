<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolunteerRequest extends Model
{
    protected $table = 'requests';

    protected $fillable = [
        'user_id',
        'type',
        'team_id',
        'subject',
        'body',
        'status',
        'admin_note',
        'submitted_at',
        'resolved_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'resolved_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
