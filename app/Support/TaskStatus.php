<?php

namespace App\Support;

/**
 * ترجمة حالة المهمة بين قاعدة البيانات والعقد.
 *
 * قاعدة البيانات: pending · in_progress · completed
 * العقد          : pending · in_progress · done
 *
 * العمود لم يُهاجَر — الترجمة هنا فقط، ولوحة الإدارة تبقى على تسميتها.
 */
class TaskStatus
{
    private const TO_CONTRACT = [
        'pending'     => 'pending',
        'in_progress' => 'in_progress',
        'completed'   => 'done',
    ];

    private const TO_DATABASE = [
        'pending'     => 'pending',
        'in_progress' => 'in_progress',
        'done'        => 'completed',
    ];

    public static function toContract(?string $status): string
    {
        return self::TO_CONTRACT[$status] ?? 'pending';
    }

    public static function toDatabase(?string $status): ?string
    {
        return self::TO_DATABASE[$status] ?? null;
    }
}
