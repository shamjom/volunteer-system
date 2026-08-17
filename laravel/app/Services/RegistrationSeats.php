<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * حجز مقاعد الفعاليات.
 *
 * المقعد محجوز ما دام التسجيل pending أو approved، ويُحرَّر عند الإلغاء أو الرفض.
 *
 * الحجز تحديث شرطي ذرّي في جملة SQL واحدة — لا قراءة ثم كتابة، فلا سباق بين
 * طلبين متزامنين. القيد CHECK على مستوى الجدول هو خط الدفاع الثاني.
 */
class RegistrationSeats
{
    /** الحالات التي تحجز مقعداً. */
    public const HOLDING_STATUSES = ['pending', 'approved'];

    /**
     * يحاول حجز مقعد. يعيد false إذا كانت الفعالية ممتلئة.
     */
    public static function reserve(int $eventId): bool
    {
        $affected = DB::update(
            'UPDATE events
             SET slots_taken = slots_taken + 1
             WHERE id = ? AND slots_taken < max_volunteers',
            [$eventId]
        );

        return $affected === 1;
    }

    /**
     * يحرّر مقعداً محجوزاً. آمن للاستدعاء المتكرر — لا ينزل تحت الصفر.
     */
    public static function release(int $eventId): void
    {
        DB::update(
            'UPDATE events
             SET slots_taken = slots_taken - 1
             WHERE id = ? AND slots_taken > 0',
            [$eventId]
        );
    }

    public static function holds(?string $registrationStatus): bool
    {
        return in_array($registrationStatus, self::HOLDING_STATUSES, true);
    }
}
