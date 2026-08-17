<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'tool',
        'arguments',
        'ok',
        'error_code',
        'confirmation_id',
        'ip',
    ];

    protected $casts = [
        'arguments' => 'array',
        'ok'        => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
