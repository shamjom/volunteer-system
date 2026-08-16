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
        'slots_taken',
        'status',
        'created_by',
    ];

    // لا تُضف casts للتواريخ هنا — ذلك يغيّر شكل تسلسل JSON في مسارات لوحة
    // الإدارة القائمة. طبقة الأدوات تحوّل التواريخ عند التمثيل.
    protected $casts = [
        'slots_taken' => 'integer',
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