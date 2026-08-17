<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('slots_taken')->default(0)->after('max_volunteers');
        });

        // المقعد محجوز ما دام التسجيل pending أو approved
        DB::statement("
            UPDATE events
            SET slots_taken = (
                SELECT COUNT(*)
                FROM event_registrations r
                WHERE r.event_id = events.id
                  AND r.registration_status IN ('pending', 'approved')
            )
        ");

        // بيانات قديمة قد تتجاوز السعة — تُقصّ قبل تفعيل القيد
        DB::statement('
            UPDATE events
            SET slots_taken = max_volunteers
            WHERE slots_taken > max_volunteers
        ');

        // القيد الذي يمنع تجاوز المقاعد حتى لو أخطأ الكود.
        // sqlite لا يدعم إضافة CHECK بعد إنشاء الجدول — الحماية هناك تبقى
        // على التحديث الشرطي الذرّي في RegistrationSeats (بيئة الاختبار فقط).
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE events
                ADD CONSTRAINT events_slots_within_capacity
                CHECK (slots_taken <= max_volunteers)
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE events DROP CHECK events_slots_within_capacity');
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('slots_taken');
        });
    }
};
